<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Class ShardRoutingService
 * Manages data distribution and retrieval logic across multiple database shards.
 */
class ShardRoutingService
{
    /**
     * Determine which shard a user belongs to using Metadata DB.
     * * @param string $email
     * @return object|null Returns mapping data (shard_id, phase_id) or null.
     */
    public function getRoute(string $email): ?object
    {
        return DB::connection('metadata')
            ->table('global_users')
            ->where('email', $email)
            ->first(['id', 'shard_id', 'phase_id', 'shard_key']);
    }

    /**
     * Decide the best shard for a new registration. 
     * Logic can be Round-robin, Least-connection, or static.
     * * @return array Configuration for the new user.
     */
    public function getDestinationForNewUser(): array
    {
        // Simple Logic: Currently we have 2 shards. 
        // We can use a random or load-balanced approach.
        $shardId = rand(1, 2);

        return [
            'shard_id'   => $shardId,
            'shard_name' => "shard_" . $shardId,
            'phase_id'   => 1, // Current architecture phase
            'shard_key'  => 'region_asia' // Example logical key
        ];
    }
}
