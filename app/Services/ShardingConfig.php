<?php

namespace App\Services;

class ShardingConfig
{
    public function getTopology(): array
    {
        return [
            1 => [
                'phase_no' => 1,
                'status'   => 'active',
                'shards'   => [
                    1 => [
                        'connection' => 'shard_1', // DB Connection Name
                        'label'      => 'Asia-South-1',
                        'region'     => 'region_asia',
                        'is_active'  => true,
                        //'weight'     => 50 //for load blancing later.
                    ],
                    2 => [
                        'connection' => 'shard_2',
                        'label'      => 'Asia-South-2',
                        'region'     => 'region_asia',
                        'is_active'  => true,
                        //'weight'     => 50 //for load blancing later.
                    ]
                ]
            ],
            // Phase 2 (Upcoming - Commented out)
            /*
            2 => [
                'phase_no' => 2,
                'status'   => 'upcoming',
                'shards'   => [
                    3 => [
                        'connection' => 'shard_3',
                        'label'      => 'Asia-South-3',
                        'region'     => 'region_asia',
                        'is_active'  => false,
                        //'weight'     => 50 //for load blancing later.
                    ],
                    4 => [
                        'connection' => 'shard_4',
                        'label'      => 'Asia-South-4',
                        'region'     => 'region_asia',
                        'is_active'  => false,
                        //'weight'     => 50 //for load blancing later.
                    ]
                ]
            ]
            */
        ];
    }

    public function getActivePhase(): array
    {
        return collect($this->getTopology())->firstWhere('status', 'active');
    }

    public function getTargetShardForNewRegistration(): array
    {
        $activePhase = $this->getActivePhase();
        // Filter only active shards
        $activeShards = collect($activePhase['shards'])->where('is_active', true)->toArray();

        $shardId = array_rand($activeShards);
        $shardDetails = $activeShards[$shardId];

        return [
            'phase_id'   => $activePhase['phase_no'],
            'shard_id'   => $shardId,
            'shard_key'  => $shardDetails['connection'],
            'shard_name' => $shardDetails['label'],
            'region'     => $shardDetails['region']
        ];
    }

    public function getAllShards(): array
    {
        $allShards = [];
        foreach ($this->getTopology() as $phase) {
            foreach ($phase['shards'] as $shard) {
                $allShards[] = $shard['connection'];
            }
        }
        return $allShards;
    }
}
