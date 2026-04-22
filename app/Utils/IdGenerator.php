<?php

namespace App\Utils;

use Illuminate\Support\Facades\Cache;
use App\Services\ShardingConfig;
/**
 * Class IdGenerator
 * Handles unique ID generation for sharded environment to avoid ID collisions.
 */
class IdGenerator
{
    /**
     * Generates a unique 64-bit integer based on microtime and random seed.
     * total length will be 21 digit - Consistent Length
     * * @return int
     */
    public static function generate($module = 'user'): string
    {
        $customEpoch = 1745210000000;
        $currentMs = (int) (microtime(true) * 1000);
        $timestamp = $currentMs - $customEpoch;
        // 2. Shard ID (2 digit)
        $shardId = str_pad(app(\App\Services\ShardingConfig::class)->getTargetShardForNewRegistration()['shard_id'], 2, '0', STR_PAD_LEFT);
        
        // 3.Sequence (4 digit)
        $sequence = str_pad(Cache::increment("{$module}_id_sequence") % 10000, 4, '0', STR_PAD_LEFT);

        // 4. final id  (10 + 2 + 4 = 16 digit)
        return "{$timestamp}{$shardId}{$sequence}";
    }
}





/* // 1. timestamp (13 digit) - present time milisecond. time mili
        $timestamp = (int) (microtime(true) * 1000);

        // 2. dynamic shard id (from ShardingConfig)
        // we are taking active shard id, so that's it can easyly undersand this data is generating in which shard.
        $config = app(ShardingConfig::class);
        $activeShard = $config->getTargetShardForNewRegistration();
        $shardId = str_pad($activeShard['shard_id'], 2, '0', STR_PAD_LEFT); // 2 digit (like: 01)

        // 3. increment sequence
        // different sequence accroding to module (like: user_id_sequence, post_id_sequence)
        $sequence = Cache::increment("{$module}_id_sequence");

        //start from 1 when cross 999999, so that's id will be not much longer. 
        if ($sequence >= 999999) {
            Cache::put("{$module}_id_sequence", 1);
        }
        $sequenceId = str_pad($sequence, 6, '0', STR_PAD_LEFT); // 6 digit (like: 000105)

        // 4. final id generation
        return "{$timestamp}{$shardId}{$sequenceId}"; */