<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserServiceOld
{
    protected $userRepo;
    protected $shardingConfig;
    protected $bloomFilter;

    public function __construct(UserRepositoryInterface $userRepo, 
        ShardingConfig $shardingConfig, BloomFilterService $bloomFilter
    
    )
    {
        $this->userRepo = $userRepo;
        $this->shardingConfig = $shardingConfig;
        $this->bloomFilter = $bloomFilter;
    }

    /**
     * Register User
     */
    public function registerUser(array $data): object
    {
        $email = $data['email'];
        $phone = $data['phone'];

        // 1 BloomFilter Check [check leyar 1]
        if ($this->bloomFilter->exists('email', $email) || $this->bloomFilter->exists('phone', $phone)) {

            // 2. if Bloom Filter return 'False Positive', check with metadata [check layer 2]
            $existsInMeta = DB::connection('metadata')->table('global_users')
                ->where('email', $email)
                ->orWhere('phone', $phone)
                ->exists();

            if ($existsInMeta) {
                throw new \Exception("User already exists with this email or phone.");
            }
        }

        // 3. if the user is not exist, then registered as a new user
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
                'phase_id'   => $target['phase_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Save in Shard
            $this->userRepo->createInShard($userData, $target['shard_key']);

            // Save in Metadata
            DB::connection('metadata')->table('global_users')->insert([
                'id'         => $userId,
                'email'      => $data['email'],
                'phone'      => $data['phone'],
                'shard_id'   => $target['shard_id'],
                'shard_key'  => $target['shard_key'],
                'phase_id'   => $target['phase_id'],
                'created_at' => now(),
            ]);

            // 4. if registered successful, then store in redis
            $this->bloomFilter->addToFilter($data['email'], $data['phone']);

            // Save sharding mapping in redis (for fast login)
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

                //If the new email/phone is not there, then it will be fetched from the metadata
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

        //Those data will be converted into an array and then converted into a model
        $user = new User();
        $user->forceFill((array) $userData); // It will certain that the id and phone will be there
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
     * Redis Update Helper
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
