<?php

namespace App\Repositories;

use App\Traits\ShardedPaginator;
use Illuminate\Support\Facades\DB;

/**
 * Class UserRepository
 * Handles all direct database interactions across shards and metadata.
 */
class UserRepository
{
    use ShardedPaginator;
    /**
     * Get metadata by ID
     */
    public function getMetadataById(int $id): ?object
    {
        return DB::connection('metadata')->table('global_users')->where('id', $id)->first();
    }

    /**
     * Get metadata by identifier (email/phone)
     */
    public function getMetadataByIdentifier(string $type, string $identifier): ?object
    {
        return DB::connection('metadata')->table('global_users')->where($type, $identifier)->first();
    }

    /**
     * Update Metadata
     */
    public function updateMetadata(int $id, array $data): bool
    {
        return DB::connection('metadata')->table('global_users')->where('id', $id)->update($data);
    }

    /**
     * Delete Metadata
     */
    public function deleteMetadata(int $id): bool
    {
        return DB::connection('metadata')->table('global_users')->where('id', $id)->delete();
    }


    /**
     * Check if a user exists in global metadata.
     */
    public function existsInMetadata(string $email, string $phone): bool
    {
        try {
            return DB::connection('metadata')->table('global_users')
                ->select('email,phone')
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
     * Get paginated users from a specific shard.
     * * @param string $shard
     * @param int $perPage
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getPaginatedFromShard(string $shard, int $perPage)
    {
        // We use the shard name to dynamically switch the connection
        return DB::connection($shard)
            ->table('users')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }
    
    /**
     * Fetch users from all shards and paginate (Global Pagination)
     * Note: For 1B records, this is usually done via a Search Engine (Elasticsearch).
     * Here we implement a database union approach for your current shards.
     */
    public function getAllUsersPaginated(array $allShards, int $perPage)
    {
        return $this->getGlobalPaginatedData($allShards, 'users', $perPage, 'total_users_count');
    }


    /**
     * Fetch user from a specific shard's replica.
     * * @param string $identifier
     * @param string $type
     * @param string $shardKey
     * @return object|null
     */
    public function findInShard(string $identifier, string $type, string $shardKey): ?object
    {
        // Laravel automatically uses the replica for read operations
        return DB::connection($shardKey)->table('users')
            ->where($type, $identifier)
            ->first();
    }

    /**
     * Fetch user from a specific shard's replica.
     * * @param string $id
     * @param string $shardKey
     * @return object|null
     */
    public function findInShardById(string $id, string $shardKey): ?object
    {
        // Laravel automatically uses the replica for read operations
        return DB::connection($shardKey)->table('users')
            ->where('id', $id)
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



    /**
     * Bulk insert users into a specific shard.
     * @param string $shard
     * @param array $data
     * @return bool
     */
    public function bulkInsertToShard(string $shard, array $data): bool
    {
        // Using DB::connection($shard) ensures we are on the right database
        return DB::connection($shard)->table('users')->insert($data);
    }

    /**
     * Bulk insert metadata mapping into metadata database.
     * @param array $data
     * @return bool
     */
    public function insertMetadata(array $data): bool
    {
        return DB::connection('metadata')->table('global_users')->insert($data);
    }
}
