<?php

namespace App\Services;

/**
 * Class ShardingConfig
 * Controls the Global Sharding Topology and Phase Management.
 */
class ShardingConfig
{
    /**
     * Define the architecture topology.
     * In a real system, this could come from a JSON file or a cached Metadata DB query.
     */
    private function getTopology(): array
    {
        return [
            1 => [
                'phase_no' => 1,
                'status'   => 'legacy', // Data exists here, but no new registrations
                'shards'   => [
                    1 => 'shard_1',
                    2 => 'shard_2'
                ]
            ],
            2 => [
                'phase_no' => 2,
                'status'   => 'active', // Currently running for new users
                'shards'   => [
                    3 => 'shard_3',
                    4 => 'shard_4',
                    5 => 'shard_5'
                ]
            ]
        ];
    }

    /**
     * Get the currently running phase for new registrations.
     */
    public function getActivePhase(): array
    {
        return collect($this->getTopology())->firstWhere('status', 'active');
    }

    /**
     * Get a random shard from the active phase.
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
