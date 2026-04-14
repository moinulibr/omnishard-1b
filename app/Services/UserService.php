<?php

namespace App\Services;

use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Class UserService
 * Orchestrates user lifecycle across multiple shards and metadata management.
 */
class UserService
{
    protected $userRepo;
    protected $shardingConfig;

    public function __construct(UserRepositoryInterface $userRepo, ShardingConfig $shardingConfig)
    {
        $this->userRepo = $userRepo;
        $this->shardingConfig = $shardingConfig;
    }

    /**
     * Register a user with Dynamic Phase & Shard Selection.
     */
    public function registerUser(array $data): object
    {
        $target = $this->shardingConfig->getTargetShardForNewRegistration();
        $userId = (int) (microtime(true) * 1000);

        return DB::transaction(function () use ($userId, $data, $target) {
            // 1. Create in Shard Master
            $this->userRepo->createInShard([
                'id'         => $userId,
                'name'       => $data['name'],
                'email'      => $data['email'],
                'phone'      => $data['phone'],
                'password'   => Hash::make($data['password']),
                'shard_key'  => $target['shard_key'],
                'created_at' => now(),
                'updated_at' => now(),
            ], $target['shard_key']);

            // 2. Insert into Metadata DB
            DB::connection('metadata')->table('global_users')->insert([
                'id'         => $userId,
                'email'      => $data['email'],
                'phone'      => $data['phone'],
                'shard_id'   => $target['shard_id'],
                'shard_key'  => $target['shard_key'],
                'phase_id'   => $target['phase_id'],
                'created_at' => now(),
            ]);

            // 3. Global Indexing (Redis)
            $this->updateRedisIndexes($userId, $data['email'], $data['phone'], $target['shard_key']);

            return (object) [
                'id' => $userId,
                'name' => $data['name'],
                'email' => $data['email'],
                'shard' => $target['shard_key']
            ];
        });
    }

    /**
     * Authenticate user across shards.
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
     * Find user using Bloom Filter -> Redis -> Metadata DB (Self-healing).
     */
    public function findUserByIdentifier(string $identifier): ?object
    {
        $type = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $redis = Redis::connection();

        $shard = $redis->get("map:{$type}:{$identifier}");

        if (!$shard) {
            $metadata = DB::connection('metadata')->table('global_users')
                ->where($type, $identifier)->first();

            if (!$metadata) return null;
            $shard = $metadata->shard_key;

            // Self-heal Redis mapping
            $redis->set("map:{$type}:{$identifier}", $shard);
        }

        return $this->userRepo->findInShard($identifier, $type, $shard);
    }

    /**
     * Update User Info across Shard and Metadata.
     */
    public function updateUser(int $id, array $data): bool
    {
        $metadata = DB::connection('metadata')->table('global_users')->where('id', $id)->first();
        if (!$metadata) return false;

        $shard = $metadata->shard_key;

        return DB::transaction(function () use ($id, $data, $shard, $metadata) {
            // Update in Shard
            $this->userRepo->updateInShard($id, $data, $shard);

            // Update Metadata if email/phone changed
            if (isset($data['email']) || isset($data['phone'])) {
                DB::connection('metadata')->table('global_users')
                    ->where('id', $id)
                    ->update(array_intersect_key($data, array_flip(['email', 'phone'])));

                // Refresh Redis Indexes
                $this->updateRedisIndexes($id, $data['email'] ?? $metadata->email, $data['phone'] ?? $metadata->phone, $shard);
            }

            return true;
        });
    }

    /**
     * Delete User from Shard, Metadata and Redis.
     */
    public function deleteUser(int $id): bool
    {
        $metadata = DB::connection('metadata')->table('global_users')->where('id', $id)->first();
        if (!$metadata) return false;

        $shard = $metadata->shard_key;

        return DB::transaction(function () use ($id, $shard, $metadata) {
            $this->userRepo->deleteInShard($id, $shard);
            DB::connection('metadata')->table('global_users')->where('id', $id)->delete();

            // Clean up Redis
            $redis = Redis::connection();
            $redis->del("map:email:{$metadata->email}");
            $redis->del("map:phone:{$metadata->phone}");
            $redis->del("map:id:{$id}");

            return true;
        });
    }

    /**
     * Helper to refresh Redis mappings.
     */
    private function updateRedisIndexes($id, $email, $phone, $shard)
    {
        $redis = Redis::connection();
        $redis->executeRaw(['BF.ADD', 'user_bloom', $email]);
        $redis->set("map:email:{$email}", $shard);
        $redis->set("map:phone:{$phone}", $shard);
        $redis->set("map:id:{$id}", $shard);
    }
}
