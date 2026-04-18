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
        $allResults = collect();
        $currentPage = \Illuminate\Pagination\Paginator::resolveCurrentPage() ?: 1;
        $offset = ($currentPage - 1) * $perPage;

        // ১. প্রতিটি শার্ড থেকে আলাদা করে ডাটা নিয়ে আসা (O(n) where n = number of shards)
        foreach ($allShards as $shard) {
            $shardResults = DB::connection($shard)
                ->table('users')
                ->select('id', 'name', 'email', 'phone', 'created_at')
                ->orderBy('created_at', 'desc')
                ->offset($offset) // গুরুত্বপূর্ণ: ডাটাবেস লেভেলেই অফসেট করা
                ->limit($perPage)
                ->get()
                ->map(function ($user) use ($shard) {
                    $user->shard_key = $shard; // শার্ড আইডেন্টিফাই করা
                    return $user;
                });

            $allResults = $allResults->concat($shardResults);
        }

        // ২. সব শার্ডের রেজাল্টকে একসাথে করে আবার সর্ট করা (পিএইচপি মেমোরিতে শুধু ৩০-৪০টি ডাটা থাকবে)
        $finalResults = $allResults->sortByDesc('created_at')->take($perPage)->values();

        // ৩. রেডিস থেকে টোটাল কাউন্ট নেওয়া (DBMS-এর ওপর চাপ কমাতে)
        $totalUsers = (int) \Illuminate\Support\Facades\Redis::get('total_users_count') ?: 0;

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $finalResults,
            $totalUsers,
            $perPage,
            $currentPage,
            ['path' => \Illuminate\Pagination\Paginator::resolveCurrentPath()]
        );
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
