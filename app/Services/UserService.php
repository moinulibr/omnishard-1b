<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Validation\ValidationException;
use App\Services\RedisCounterService;
use App\Utils\IdGenerator;
use Exception;

/**
 * Class UserService
 * Orchestrates business logic for user management and sharding failover.
 */
class UserService
{
    protected $userRepo;
    protected $shardingConfig;
    protected $bloomFilter;
    protected $redisServiceCounter;

    public function __construct(
        UserRepository $userRepo,
        ShardingConfig $shardingConfig,
        BloomFilterService $bloomFilter,
        RedisCounterService $redisServiceCounter
        ) {
        $this->userRepo = $userRepo;
        $this->shardingConfig = $shardingConfig;
        $this->bloomFilter = $bloomFilter;
        $this->redisServiceCounter = $redisServiceCounter;
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
        //$userId = (int) (microtime(true) * 1000);
        $userId = IdGenerator::generate('user');

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

        Redis::incr('total_users_count');
        
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
    public function getGlobalUsersData(int $perPage = 15)
    {
        // 1. Get dynamic shards from Config
        //$shards = app(\App\Services\ShardingConfig::class)->getAllShards();
        $shards = $this->shardingConfig->getAllShards();
        $table = "users"; 
        $select = ['id', 'name', 'email', 'phone','shard_key','phase_id','created_at'];
        $orderBy = "ASC";
        $totalCount = 'total_users_count';
        return $this->userRepo->getAllUsersPaginated($shards, $table, $perPage, $orderBy, $totalCount, $select);
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



    public function updateProfile(int $userId, array $newData)
    {
        // 1. Fetch user from Metadata to locate their shard
        $metaUser = DB::connection('metadata')
            ->table('global_users')
            ->where('id', $userId)
            ->first();

        if (!$metaUser) {
            throw new \Exception("User not found in global index.");
        }

        $shardConnection = "shard_" . $metaUser->shard_id;
        $emailChanged = isset($newData['email']) && $newData['email'] !== $metaUser->email;

        // 2. Start Atomic Transaction
        // Since we are dealing with two different DBs, we manually handle the rollback logic
        try {
            DB::connection('metadata')->beginTransaction();
            DB::connection($shardConnection)->beginTransaction();

            // 3. Handle Email Change & Uniqueness
            if ($emailChanged) {
                $isEmailTaken = DB::connection('metadata')
                    ->table('global_users')
                    ->where('email', $newData['email'])
                    ->where('id', '!=', $userId)
                    ->exists();

                if ($isEmailTaken) {
                    throw new \Exception("The email address is already in use by another account.");
                }

                // Update Metadata DB
                DB::connection('metadata')
                    ->table('global_users')
                    ->where('id', $userId)
                    ->update(['email' => $newData['email']]);
            }

            // 4. Handle Password Hashing (Scenario 4)
            if (isset($newData['password'])) {
                $newData['password'] = Hash::make($newData['password']);
            }

            // 5. Update Shard DB
            DB::connection($shardConnection)
                ->table('users')
                ->where('id', $userId)
                ->update($newData);

            // Commit both connections
            DB::connection('metadata')->commit();
            DB::connection($shardConnection)->commit();

            // 6. Post-Update Action: Clear Redis Cache if necessary
            if ($emailChanged || isset($newData['password'])) {
                $this->revokeUserSessions($userId);
            }

            return true;
        } catch (\Exception $e) {
            // Rollback both if anything fails
            DB::connection('metadata')->rollBack();
            DB::connection($shardConnection)->rollBack();
            throw $e;
        }
    }

    /**
     * Revoke all active sessions in Redis for high security after critical updates
     */
    private function revokeUserSessions($userId)
    {
        // Logic to scan Redis for token_shard prefix and delete matches for this user
        // This forces a re-login for security
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

    public function findUserById(string $id): ?User
    {
        $shard = $this->userRepo->getMetadataById($id);
        if(!$shard) return null;
        
        $userData = $this->userRepo->findInShardById($id, $shard->shard_key);
        if (!$userData) return null;

        $user = new User();
        $user->forceFill((array) $userData);
        $user->exists = true;
        $user->setConnection($shard->shard_key);

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

        // Generate token on the user's specific shard connection
        // Crucial: Set the connection to the user's shard before creating token
        // 1. Ensure the connection is set to the user's shard
        $user->setConnection($user->shard_key);

        // 2. Create the token
        $tokenResult = $user->createToken('auth_token');
        $plainToken = $tokenResult->plainTextToken;

        // 3. Extract the real token (remove the ID part before hashing)
        // If token is "14|abc123token", we need "abc123token"
        $tokenValue = str_contains($plainToken, '|') ? explode('|', $plainToken)[1] : $plainToken;
        $hashedToken = hash('sha256', $tokenValue);

        // 4. Store in Redis mapping (Valid for 24 hours)
        \Illuminate\Support\Facades\Redis::setex("token_shard:{$hashedToken}", 86400, $user->shard_key);

        return [
            'token' => $plainToken,
            'user'  => $user
        ];
        return $user;
    }


    /**
     * Logout logic: Clear Redis discovery maps for the user session if needed.
     */
    public function logoutUser(Request $request): bool
    {
        $user = $request->user();
        if (!$user) return false;

        // 1. Get the current token from the authenticated user
        $currentToken = $user->currentAccessToken();

        if ($currentToken) {
            // 2. Identify the token string to clear from Redis
            // Sanctum stores the hashed token in the 'token' column
            $hashedToken = $currentToken->token;

            // 3. Clear the Redis Shard Map immediately
            \Illuminate\Support\Facades\Redis::del("token_shard:{$hashedToken}");

            // 4. Delete the token from the Shard's personal_access_tokens table
            return $currentToken->delete();
        }
        return false;
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

            $this->redisServiceCounter->decrement('down');
            
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
