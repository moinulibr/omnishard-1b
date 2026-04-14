<?php

namespace App\Services;

/**
 * Class ShardingConfig
 * Manages the global sharding topology and dynamic phase routing.
 */
class ShardingConfig
{
    /**
     * Get the global sharding topology.
     * Status: 'active' (accepts new writes), 'legacy' (read-only/existing data).
     * * @return array
     */
    public function getTopology(): array
    {
        return [
            1 => [
                'phase_no' => 1,
                'status'   => 'active', // Currently we are in Phase 1
                'shards'   => [
                    1 => 'shard_1',
                    2 => 'shard_2'
                ]
            ],
            // Future phases will be added here
            /*
            2 => [
                'phase_no' => 2,
                'status'   => 'upcoming',
                'shards'   => [3 => 'shard_3', 4 => 'shard_4']
            ]
            */
        ];
    }

    /**
     * Get the current active phase for registration.
     */
    public function getActivePhase(): array
    {
        return collect($this->getTopology())->firstWhere('status', 'active');
    }

    /**
     * Pick a random shard from the active phase.
     */
    public function getTargetShardForNewRegistration(): array
    {
        $activePhase = $this->getActivePhase();
        $shardKey = array_rand($activePhase['shards']);

        return [
            'phase_id'  => $activePhase['phase_no'],
            'shard_id'  => $shardKey,
            'shard_key' => $activePhase['shards'][$shardKey]
        ];
    }
}
