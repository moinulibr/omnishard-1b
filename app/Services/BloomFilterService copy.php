<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Class BloomFilterService
 * Optimized for O(1) membership checks with 1B+ capacity.
 */
class BloomFilterServiceOld
{
    private array $phases = [
        [
            'id' => 1,
            'bucket_size' => 1000000000, // 1 Billion
            'key_prefix' => 'bf_v1'
        ],
        /* // Scaling for Phase 2 (Uncomment when users reach 1B+)
        [
            'id' => 2, 
            'bucket_size' => 1000000000, // Another 1 Billion
            'key_prefix' => 'bf_v2'
        ], 
        */
    ];

    public function addToFilter(string $email, string $phone): void
    {
        $activePhase = end($this->phases);
        $this->setBit($activePhase, 'email', $email);
        $this->setBit($activePhase, 'phone', $phone);
    }

    public function exists(string $type, string $value): bool
    {
        foreach ($this->phases as $phase) {
            $pos = $this->getHash($value, $phase['bucket_size']);
            $key = $phase['key_prefix'] . '_' . $type;

            if (Redis::getbit($key, $pos) === 1) {
                return true;
            }
        }
        return false;
    }

    private function setBit(array $phase, string $type, string $value): void
    {
        $pos = $this->getHash($value, $phase['bucket_size']);
        $key = $phase['key_prefix'] . '_' . $type;
        Redis::setbit($key, $pos, 1);
    }

    private function getHash(string $input, int $bucketSize): int
    {
        return abs(crc32($input)) % $bucketSize;
    }
}
