<?php

namespace App\Services;

class ShardingConfig
{
    public function getTopology(): array
    {
        //shard (list) config under phase 
        return [
            1 => [
                'phase_no' => 1,
                'status'   => 'active',
                'shards'   => [
                    1 => [
                        'shard_key'  => 'shard_1',//unique
                        'shard_name' => 'Asia South 1',
                        'region'     => 'region_asia'
                    ],
                    2 => [
                        'shard_key'  => 'shard_2',//unique
                        'shard_name' => 'Asia South 2',
                        'region'     => 'region_asia'
                    ]
                ]
            ],
            // Future phases will be added here
            /* 2 => [
                'phase_no' => 2,
                'status'   => 'upcoming',
                'shards'   => [
                    3 => [
                        'shard_key'  => 'shard_3',//unique
                        'shard_name' => 'Asia South 3',
                        'region'     => 'region_asia'
                    ],
                    4 => [
                        'shard_key'  => 'shard_4',//unique
                        'shard_name' => 'Asia South 4',
                        'region'     => 'region_asia'
                    ]
                ]
            ] */
        ];
    }
    

    public function getActivePhase(): array
    {
        return collect($this->getTopology())->firstWhere('status', 'active');
    }

    public function getTargetShardForNewRegistration(): array
    {
        $activePhase = $this->getActivePhase();
        $shardId = array_rand($activePhase['shards']);
        $shardDetails = $activePhase['shards'][$shardId];

        return [
            'phase_id'   => $activePhase['phase_no'],
            'shard_id'   => $shardId,
            'shard_key'  => $shardDetails['shard_key'],
            'shard_name' => $shardDetails['shard_name'],
            'region'     => $shardDetails['region']
        ];
    }

    // Here will be implemented ShardRoutingService
    public function getAllShards(): array
    {
        $allShards = [];
        foreach ($this->getTopology() as $phase) {
            foreach ($phase['shards'] as $shard) {
                $allShards[] = $shard['shard_key'];
            }
        }
        return $allShards;
    }
}
