<?php

namespace App\Repositories\Interfaces;

/**
 * Interface UserRepositoryInterface
 * Defines the contract for user-related data operations across shards and metadata.
 */
interface UserRepositoryInterface
{
    /**
     * Check if a user exists in global metadata.
     */
    public function existsInMetadata(string $email, string $phone): bool;

    /**2
     * Fetch a user from a specific shard.
     */
    public function findInShard(string $identifier, string $type, string $shard): ?object;

    /**
     * Create a new user record in a specific shard.
     */
    public function createInShard(array $data, string $shard): bool;

    /**
     * Create a record in the global metadata table.
     */
    public function createInMetadata(array $data): bool;

    /**
     * Update user in a specific shard.
     */
    public function updateInShard(int $id, array $data, string $shard): bool;

    /**
     * Delete user from a specific shard.
     */
    public function deleteInShard(int $id, string $shard): bool;
}
