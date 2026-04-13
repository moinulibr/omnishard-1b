<?php

namespace App\Repositories\Interfaces;

/**
 * Interface UserRepositoryInterface
 * Defines the contract for user-related data operations.
 */
interface UserRepositoryInterface
{
    /**
     * @param string $identifier
     * @param string $type
     * @param string $shard
     * @return object|null
     */
    public function findInShard(string $identifier, string $type, string $shard): ?object;
}
