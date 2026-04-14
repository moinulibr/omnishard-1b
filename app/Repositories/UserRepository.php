<?php

namespace App\Repositories;

use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Class UserRepository
 * Implements data fetching logic from specific shards.
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * Fetch user from a specific shard's replica.
     * * @param string $identifier
     * @param string $type
     * @param string $shard
     * @return object|null
     */
    public function findInShard(string $identifier, string $type, string $shard): ?object
    {
        // Laravel automatically uses the replica for read operations
        return DB::connection($shard)
            ->table('users')
            ->where($type, $identifier)
            ->first();
    }

    /**
     * @inheritDoc
     */
    public function createInShard(array $data, string $shard): int
    {
        return DB::connection($shard)
            ->table('users')
            ->insertGetId($data);
    }
}
