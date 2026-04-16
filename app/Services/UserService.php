<?php

namespace App\Services;

use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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
