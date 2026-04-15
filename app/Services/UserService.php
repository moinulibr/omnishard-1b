<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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
     * Register User
     */
    public function registerUser(array $data): object
    {
        $target = $this->shardingConfig->getTargetShardForNewRegistration();
        $userId = (int) (microtime(true) * 1000);

        return DB::transaction(function () use ($userId, $data, $target) {
            $userData = [
                'id'         => $userId,
                'name'       => $data['name'],
                'email'      => $data['email'],
                'phone'      => $data['phone'],
                'password'   => Hash::make($data['password']),
                'shard_key'  => $target['shard_key'],
                'phase_id'   => $target['phase_id'], //added phase
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $this->userRepo->createInShard($userData, $target['shard_key']);

            DB::connection('metadata')->table('global_users')->insert([
                'id'         => $userId,
                'email'      => $data['email'],
                'phone'      => $data['phone'],
                'shard_id'   => $target['shard_id'],
                'shard_key'  => $target['shard_key'],
                'phase_id'   => $target['phase_id'],
                'created_at' => now(),
            ]);

            $this->updateRedisIndexes($userId, $data['email'], $data['phone'], $target['shard_key']);

            return (object) $userData;
        });
    }

    /**
     * Update User
     */
    public function updateUser(int $id, array $data): bool
    {
        $metadata = DB::connection('metadata')->table('global_users')->where('id', $id)->first();
        if (!$metadata) return false;

        $shard = $metadata->shard_key;

        return DB::transaction(function () use ($id, $data, $shard, $metadata) {
            $this->userRepo->updateInShard($id, $data, $shard);

            if (isset($data['email']) || isset($data['phone'])) {
                $updateData = array_intersect_key($data, array_flip(['email', 'phone']));

                DB::connection('metadata')->table('global_users')
                    ->where('id', $id)
                    ->update($updateData);

                // এখানে নিশ্চিত করছি যে যদি নতুন ইমেইল/ফোন না থাকে তবে মেটাডাটা থেকে নিবে
                $currentEmail = $data['email'] ?? $metadata->email;
                $currentPhone = $data['phone'] ?? $metadata->phone;

                $this->updateRedisIndexes($id, $currentEmail, $currentPhone, $shard);
            }

            return true;
        });
    }

    /**
     * Find User
     */
    public function findUserByIdentifier(string $identifier): ?User
    {
        $type = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $redis = Redis::connection();
        $shard = $redis->get("map:{$type}:{$identifier}");

        if (!$shard) {
            $metadata = DB::connection('metadata')->table('global_users')->where($type, $identifier)->first();
            if (!$metadata) return null;
            $shard = $metadata->shard_key;
        }

        $userData = $this->userRepo->findInShard($identifier, $type, $shard);
        if (!$userData) return null;

        // ডাটাগুলোকে অ্যারেতে কনভার্ট করে মডেলে ঢুকাতে হবে
        $user = new User();
        $user->forceFill((array) $userData); // এটি নিশ্চিত করবে আইডি এবং ফোন সব ফিল্ড আসবে
        $user->exists = true;
        $user->setConnection($shard);

        return $user;
    }

    /**
     * Authenticate
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
     * Delete
     */
    public function deleteUser(int $id): bool
    {
        $metadata = DB::connection('metadata')->table('global_users')->where('id', $id)->first();
        if (!$metadata) return false;

        $shard = $metadata->shard_key;

        return DB::transaction(function () use ($id, $shard, $metadata) {
            $this->userRepo->deleteInShard($id, $shard);
            DB::connection('metadata')->table('global_users')->where('id', $id)->delete();

            $redis = Redis::connection();
            $redis->del("map:email:{$metadata->email}");
            $redis->del("map:phone:{$metadata->phone}");
            $redis->del("map:id:{$id}");

            return true;
        });
    }

    /**
     * Redis Update Helper (নিশ্চিত করছি যেন এখানে প্রপার্টি এরর না হয়)
     */
    private function updateRedisIndexes($id, $email, $phone, $shard)
    {
        $redis = Redis::connection();
        $redis->executeRaw(['BF.ADD', 'user_bloom', $email]);
        $redis->set("map:email:{$email}", $shard);
        $redis->set("map:id:{$id}", $shard);
        if ($phone) {
            $redis->set("map:phone:{$phone}", $shard);
        }
    }
}
