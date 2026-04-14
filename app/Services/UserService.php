<?php

namespace App\Services;

use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Class UserService
 * Manages sharded user lifecycle, authentication, and global indexing.
 */
class UserService
{
    protected $userRepo;

    public function __construct(UserRepositoryInterface $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    /**
     * Register and Shard User (Phase 1 Logic)
     */
    public function registerUser(array $data): object
    {
        // Phase 1: Using 2 Shards
        $shardMap = ['shard_1' => 1, 'shard_2' => 2];
        $selectedShard = array_rand($shardMap);
        $shardNumericId = $shardMap[$selectedShard];
        $currentPhaseId = 1;

        $userId = (int) (microtime(true) * 1000);

        return DB::transaction(function () use ($userId, $data, $selectedShard, $shardNumericId, $currentPhaseId) {
            // 1. Write to Shard (Master)
            $this->userRepo->createInShard([
                'id'         => $userId,
                'name'       => $data['name'],
                'email'      => $data['email'],
                'phone'      => $data['phone'],
                'password'   => Hash::make($data['password']),
                'shard_key'  => $selectedShard,
                'created_at' => now(),
                'updated_at' => now(),
            ], $selectedShard);

            // 2. Write to Metadata DB
            DB::connection('metadata')->table('global_users')->insert([
                'id'         => $userId,
                'email'      => $data['email'],
                'phone'      => $data['phone'],
                'shard_id'   => $shardNumericId,
                'shard_key'  => $selectedShard,
                'phase_id'   => $currentPhaseId,
                'created_at' => now(),
            ]);

            // 3. Update Redis (Using simple keys to avoid prefix confusion in CLI)
            $redis = Redis::connection();
            $redis->executeRaw(['BF.ADD', 'user_bloom', $data['email']]);
            $redis->set("map:email:{$data['email']}", $selectedShard);
            $redis->set("map:id:{$userId}", $selectedShard);

            return (object) [
                'id' => $userId,
                'name' => $data['name'],
                'email' => $data['email'],
                'shard' => $selectedShard
            ];
        });
    }

    /**
     * Authentication Logic for Sharded Environment
     */
    public function login(string $email, string $password)
    {
        // Find shard first
        $user = $this->findUserByIdentifier($email);

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }

        return $user;
    }

    /**
     * Search with Replica Support & Self-Healing
     */
    public function findUserByIdentifier(string $identifier): ?object
    {
        $type = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $redis = Redis::connection();

        // 1. Try Redis
        $shard = $redis->get("map:{$type}:{$identifier}");

        // 2. Phase 1 Fallback: Metadata DB
        if (!$shard) {
            $metadata = DB::connection('metadata')->table('global_users')
                ->where($type, $identifier)->first();

            if (!$metadata) return null;
            $shard = $metadata->shard_key;

            // Re-warm Redis
            $redis->set("map:{$type}:{$identifier}", $shard);
        }

        // 3. Read from Shard (Replica automatically used by Laravel read configuration)
        return $this->userRepo->findInShard($identifier, $type, $shard);
    }
}
