<?php

namespace App\Services;

use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Redis;

/**
 * Class UserSearchService
 * Orchestrates Bloom Filter, Redis Mapping, and Repository calls.
 */
class UserSearchService
{
    /** @var UserRepositoryInterface */
    protected $userRepo;

    public function __construct(UserRepositoryInterface $userRepo)
    {
        $this->userRepo = $userRepo;
    }

    /**
     * Search strategy: Bloom Filter -> Redis Map -> Shard Repository.
     * * @param string $identifier
     * @param string $type
     * @return object|null
     */
    public function getRoutedUser(string $identifier, string $type): ?object
    {
        // 1. Bloom Filter Check
        $exists = Redis::executeRaw(['BF.EXISTS', 'user_bloom', $identifier]);
        if (!$exists) return null;

        // 2. Get Shard from Redis
        $shard = Redis::get("map:{$type}:{$identifier}");
        if (!$shard) return null;

        // 3. Fetch via Repository
        return $this->userRepo->findInShard($identifier, $type, $shard);
    }
}
