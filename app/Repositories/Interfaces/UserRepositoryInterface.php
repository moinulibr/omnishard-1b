<?php

namespace App\Repositories\Interfaces;

/**
 * Interface UserRepositoryInterface
 * Defines the contract for user-related data operations across shards.
 */
interface UserRepositoryInterface
{
    /**
     * Fetch a user from a specific shard.
     *
     * @param string $identifier
     * @param string $type
     * @param string $shard
     * @return object|null
     */
    public function findInShard(string $identifier, string $type, string $shard): ?object;

    /**
     * Create a new user record in a specific shard.
     *
     * @param array<string, mixed> $data
     * @param string $shard
     * @return int
     */
    public function createInShard(array $data, string $shard): int;

    public function updateInShard(int $id, array $data, string $shard): bool;
    public function deleteInShard(int $id, string $shard): bool;
}
