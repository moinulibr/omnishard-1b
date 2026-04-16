<?php

namespace App\Repositories;

use Illuminate\Support\Facades\DB;

/**
 * Class UserRepository
 * Handles all direct database interactions across shards and metadata.
 */
class UserRepository
{
    /**
     * Check if a user exists in global metadata.
     */
    public function existsInMetadata(string $email, string $phone): bool
    {
        try {
            return DB::connection('metadata')->table('global_users')
                ->where('email', $email)
                ->orWhere('phone', $phone)
                ->exists();
        } catch (\Exception $e) {
            // If metadata fails, we return false to trigger failover search in Service
            return false;
        }
    }

    /**
     * Insert user into a specific shard.
     */
    public function createInShard(array $data, string $shardKey): void
    {
        DB::connection($shardKey)->table('users')->insert($data);
    }

    /**
     * Insert record into metadata database.
     */
    public function createInMetadata(array $data): void
    {
        DB::connection('metadata')->table('global_users')->insert($data);
    }

    /**
     * Search for user in a specific shard.
     */
    public function findInShard(string $identifier, string $type, string $shardKey)
    {
        return DB::connection($shardKey)->table('users')
            ->where($type, $identifier)
            ->first();
    }


    public function updateInShard(int $id, array $data, string $shard): bool
    {
        return DB::connection($shard)->table('users')
            ->where('id', $id)
            ->update($data);
    }

    public function deleteInShard(int $id, string $shard): bool
    {
        return DB::connection($shard)->table('users')
            ->where('id', $id)
            ->delete();
    }
}
