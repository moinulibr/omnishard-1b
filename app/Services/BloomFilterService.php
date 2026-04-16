<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class BloomFilterService
{
    protected $shardingConfig;

    public function __construct(ShardingConfig $shardingConfig)
    {
        $this->shardingConfig = $shardingConfig;
    }

    public function addToFilter(string $email, string $phone): void
    {
        // Active Phase from ShardingConfig
        $activePhase = $this->shardingConfig->getActivePhase();
        $prefix = 'bf_phase_' . $activePhase['phase_no'];

        // 1 Billion Bucket Size
        $bucketSize = 1000000000;

        $this->setBit($prefix . '_email', $email, $bucketSize);
        $this->setBit($prefix . '_phone', $phone, $bucketSize);
    }

    public function exists(string $type, string $value): bool
    {
        // Check All Phase (Phase 1, Phase 2...)
        $topology = $this->shardingConfig->getTopology();
        $bucketSize = 1000000000;

        foreach ($topology as $phase) {
            $key = 'bf_phase_' . $phase['phase_no'] . '_' . $type;
            $pos = $this->getHash($value, $bucketSize);

            if (Redis::getbit($key, $pos) === 1) {
                return true;
            }
        }
        return false;
    }

    private function setBit(string $key, string $value, int $bucketSize): void
    {
        $pos = $this->getHash($value, $bucketSize);
        Redis::setbit($key, $pos, 1);
    }

    private function getHash(string $input, int $bucketSize): int
    {
        return abs(crc32($input)) % $bucketSize;
    }
}
