<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/** No Need this file. It will be remove later
 * 
 * 
 * Class ShardRoutingService
 * Manages data distribution and retrieval logic across multiple database shards.
 */
class ShardRoutingService
{
    /**
     * Dynamically select a shard from global config.
     * @return array
     */
    public function getNextAvailableDestination(): array
    {
        $shards = Config::get('sharding.active_shards');
        $selectedShard = $shards[array_rand($shards)]; // Global management

        return [
            'shard_name' => $selectedShard,
            'shard_id'   => (int) filter_var($selectedShard, FILTER_SANITIZE_NUMBER_INT),
            'phase_id'   => Config::get('sharding.current_phase'),
            'shard_key'  => 'region_asia'
        ];
    }

    /**
     * Get all registered shards from config.
     * Useful for Migrations or Mass-Seeding.
     */
    public function getAllShards(): array
    {
        return Config::get('sharding.active_shards');
    }
}
