<?php

namespace App\Utils;

/**
 * Class IdGenerator
 * Handles unique ID generation for sharded environment to avoid ID collisions.
 */
class IdGenerator
{
    /**
     * Generates a unique 64-bit integer based on microtime and random seed.
     * * @return int
     */
    public static function generate(): int
    {
        return (int) (microtime(true) * 10000) . rand(100, 999);
    }
}
