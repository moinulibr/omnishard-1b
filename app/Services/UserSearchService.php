<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Class UserSearchService
 * High-performance service to route and fetch user data across shards.
 */
class UserSearchService
{
    /**
     * Finds a user by email or phone using Redis-first routing.
     * * @param string $identifier
     * @param string $type ('email' or 'phone')
     * @return object|null
     */
    public function findUser(string $identifier, string $type = 'email'): ?object
    {
        // Step 1: Check Bloom Filter (Is it even possible that this user exists?)
        $exists = Redis::executeRaw(['BF.EXISTS', 'user_bloom', $identifier]);

        if (!$exists) {
            return null; // Instant rejection, zero DB load
        }

        // Step 2: Get Shard mapping from Redis
        $shard = Redis::get("map:{$type}:{$identifier}");

        if (!$shard) {
            // Fallback: Check Metadata DB if Redis mapping is missing
            $metadata = DB::connection('metadata')->table('global_users')
                ->where($type, $identifier)
                ->first();

            if (!$metadata) return null;
            $shard = $metadata->shard_id;
        }

        // Step 3: Fetch data from the Shard's REPLICA
        // Laravel automatically uses the 'read' config (replica) for this connection
        return DB::connection($shard)->table('users')
            ->where($type, $identifier)
            ->first();
    }
}
