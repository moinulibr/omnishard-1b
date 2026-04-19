<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Class RedisCounterService
 * A generic service to manage atomic counters in Redis for various entities.
 */
class RedisCounterService
{
    /**
     * Increment the counter for a specific key.
     * @param string $key
     * @return int
     */
    public function increment(string $key): int
    {
        return Redis::incr($key);
    }

    /**
     * Decrement the counter for a specific key.
     * @param string $key
     * @return int
     */
    public function decrement(string $key): int
    {
        return Redis::decr($key);
    }

    /**
     * Increment by a specific amount (Bulk).
     * @param string $key
     * @param int $amount
     * @return int
     */
    public function incrementBy(string $key, int $amount): int
    {
        return Redis::incrby($key, $amount);
    }

    /**
     * Manually set a specific value for a counter.
     * @param string $key
     * @param int $value
     * @return void
     */
    public function set(string $key, int $value): void
    {
        Redis::set($key, $value);
    }

    /**
     * Get the current value of a counter.
     * @param string $key
     * @return int
     */
    public function get(string $key): int
    {
        return (int) Redis::get($key) ?: 0;
    }

    /**
     * Remove the counter key from Redis.
     * @param string $key
     * @return void
     */
    public function delete(string $key): void
    {
        Redis::del($key);
    }
}