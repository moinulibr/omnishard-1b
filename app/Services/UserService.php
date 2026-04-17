<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;

/**
 * Class UserService
 * Orchestrates business logic for user management and sharding failover.
 */
class UserService
{
    protected $userRepo;
    protected $shardingConfig;
    protected $bloomFilter;

    public function __construct(
        UserRepository $userRepo,
        ShardingConfig $shardingConfig,
        BloomFilterService $bloomFilter
    ) {
        $this->userRepo = $userRepo;
        $this->shardingConfig = $shardingConfig;
        $this->bloomFilter = $bloomFilter;
    }

    /**
     * Register a new user with duplicate prevention and metadata failover.
     */
    public function registerUser(array $data): object
    {
        $email = $data['email'];
        $phone = $data['phone'];

        // 1. Check Bloom Filter first (O(1) complexity)
        if ($this->bloomFilter->exists('email', $email) || $this->bloomFilter->exists('phone', $phone)) {

            // 2. Check Metadata DB
            $exists = $this->userRepo->existsInMetadata($email, $phone);

            // 3. Metadata Failover: If metadata returns false, verify manually across all shards
            if (!$exists) {
                $exists = $this->verifyInAllShards($email, $phone);
            }

            if ($exists) {
                throw new \Exception("User already exists in the system.");
            }
        }

        $target = $this->shardingConfig->getTargetShardForNewRegistration();
        $userId = (int) (microtime(true) * 1000);

        // Prepare data
        $userData = [
            'id'         => $userId,
            'name'       => $data['name'],
            'email'      => $email,
            'phone'      => $phone,
            'password'   => Hash::make($data['password']),
            'shard_key'  => $target['shard_key'],
            'phase_id'   => $target['phase_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Process insertion
        $this->userRepo->createInShard($userData, $target['shard_key']);

        $this->userRepo->createInMetadata([
            'id'         => $userId,
            'email'      => $email,
            'phone'      => $phone,
            'shard_id'   => $target['shard_id'],
            'shard_key'  => $target['shard_key'],
            'phase_id'   => $target['phase_id'],
            'created_at' => now(),
        ]);

        // Post-registration sync
        $this->bloomFilter->addToFilter($email, $phone);

        return (object) $userData;
    }

    /**
     * Get users list with pagination. 
     * Since data is sharded, we fetch from a specific shard or aggregate.
     */
    public function getUsersList(string $shardKey, int $perPage = 15)
    {
        return $this->userRepo->getPaginatedFromShard($shardKey, $perPage);
    }

    /**
     * Get global user list across all shards.
     */
    public function getGlobalUsers(int $perPage = 15)
    {
        $shards = $this->shardingConfig->getAllShards();
        return $this->userRepo->getAllUsersPaginated($shards, $perPage);
    }

    /**
     * Search user and return formatted data.
     */
    public function searchUser(string $identifier)
    {
        $type = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        // Try Redis first for O(1) discovery
        $shard = Redis::get("map:{$type}:{$identifier}");

        if (!$shard) {
            $metadata = $this->userRepo->getMetadataByIdentifier($type, $identifier);
            if (!$metadata) return null;
            $shard = $metadata->shard_key;
        }

        return $this->userRepo->findInShard($identifier, $type, $shard);
    }

    
    /**
     * UserService.php
     */

    public function updateUser(int $id, array $data): bool
    {
        $metadata = $this->userRepo->getMetadataById($id);
        if (!$metadata) return false;

        $shard = $metadata->shard_key;

        return DB::transaction(function () use ($id, $data, $shard, $metadata) {
            $this->userRepo->updateInShard($id, $data, $shard);

            if (isset($data['email']) || isset($data['phone'])) {
                $updateData = array_intersect_key($data, array_flip(['email', 'phone']));
                $this->userRepo->updateMetadata($id, $updateData);

                $currentEmail = $data['email'] ?? $metadata->email;
                $currentPhone = $data['phone'] ?? $metadata->phone;

                $this->updateRedisIndexes($id, $currentEmail, $currentPhone, $shard);
            }
            return true;
        });
    }

    public function findUserByIdentifier(string $identifier): ?User
    {
        $type = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $redis = Redis::connection();
        $shard = $redis->get("map:{$type}:{$identifier}");

        if (!$shard) {
            $metadata = $this->userRepo->getMetadataByIdentifier($type, $identifier);
            if (!$metadata) return null;
            $shard = $metadata->shard_key;
        }

        $userData = $this->userRepo->findInShard($identifier, $type, $shard);
        if (!$userData) return null;

        $user = new User();
        $user->forceFill((array) $userData);
        $user->exists = true;
        $user->setConnection($shard);

        return $user;
    }

    /**
     * Authenticate
     */
    /**
     * Authenticate user across distributed shards.
     */
    public function login(string $email, string $password)
    {
        $user = $this->findUserByIdentifier($email);

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        return $user;
    }


    /**
     * Logout logic: Clear Redis discovery maps for the user session if needed.
     */
    public function logout(int $userId): void
    {
        $metadata = $this->userRepo->getMetadataById($userId);
        if ($metadata) {
            // If using Sanctum
            $metadata->currentAccessToken()->delete();

            $redis = Redis::connection();
            $redis->del("map:id:{$userId}");
        }
    }

    /**
     * Delete user from both shard and metadata, and clear cache.
     */
    public function deleteUser(int $id): bool
    {
        $metadata = $this->userRepo->getMetadataById($id);
        if (!$metadata) return false;

        $shard = $metadata->shard_key;

        return DB::transaction(function () use ($id, $shard, $metadata) {
            $this->userRepo->deleteInShard($id, $shard);
            $this->userRepo->deleteMetadata($id);

            // Cleanup Redis Maps
            $redis = Redis::connection();
            $redis->del("map:email:{$metadata->email}");
            $redis->del("map:phone:{$metadata->phone}");
            $redis->del("map:id:{$id}");

            return true;
        });
    }

    /**
     * Helper to keep Redis Discovery Maps updated.
     */
    private function updateRedisIndexes($id, $email, $phone, $shard)
    {
        $redis = Redis::connection();
        // Add to Bloom Filter for membership check
        $this->bloomFilter->addToFilter($email, $phone);

        // Add to Redis Hash Map for fast routing (O(1) Discovery)
        $redis->set("map:email:{$email}", $shard);
        $redis->set("map:id:{$id}", $shard);
        if ($phone) {
            $redis->set("map:phone:{$phone}", $shard);
        }
    }


    /**
     * Failover Strategy: Iterates through all active shards to find a user.
     * Used only when Metadata DB is unreachable or Bloom Filter gives a false positive.
     */
    private function verifyInAllShards(string $email, string $phone): bool
    {
        $allShards = $this->shardingConfig->getAllShards();

        foreach ($allShards as $shard) {
            $userByEmail = $this->userRepo->findInShard($email, 'email', $shard);
            $userByPhone = $this->userRepo->findInShard($phone, 'phone', $shard);

            if ($userByEmail || $userByPhone) {
                Log::warning("User found via Shard-Scan failover in: " . $shard);
                return true;
            }
        }

        return false;
    }
}
