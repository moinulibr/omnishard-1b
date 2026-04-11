<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

/**
 * Class BloomFilterService
 * Handles probabilistic membership checks for 1 Billion+ users using Redis Bitmaps.
 * This ensures O(1) time complexity and minimal memory footprint.
 */
class BloomFilterService
{
    // 1 Billion bits range (Approx 125MB) to handle high volume with low collision.
    private int $bucketSize = 1000000000;

    // Redis keys for separating email and phone filters
    private string $emailKey = 'bf_email_filter';
    private string $phoneKey = 'bf_phone_filter';

    /**
     * Set bits for both email and phone during registration.
     * * @param string $email User email address
     * @param string $phone User phone number
     * @return void
     */
    public function addToFilter(string $email, string $phone): void
    {
        // Calculate bit positions using crc32 hash
        $emailPos = $this->getHashPosition($email);
        $phonePos = $this->getHashPosition($phone);

        // Atomic bit sets in Redis
        Redis::setbit($this->emailKey, $emailPos, 1);
        Redis::setbit($this->phoneKey, $phonePos, 1);
    }

    /**
     * Check if the email potentially exists in the system.
     * * @param string $email
     * @return bool Returns true if email might exist, false if it definitely doesn't.
     */
    public function hasEmail(string $email): bool
    {
        $pos = $this->getHashPosition($email);
        return Redis::getbit($this->emailKey, $pos) === 1;
    }

    /**
     * Check if the phone number potentially exists in the system.
     * * @param string $phone
     * @return bool
     */
    public function hasPhone(string $phone): bool
    {
        $pos = $this->getHashPosition($phone);
        return Redis::getbit($this->phoneKey, $pos) === 1;
    }

    /**
     * Generate a consistent bit position based on input string.
     * * @param string $input
     * @return int
     */
    private function getHashPosition(string $input): int
    {
        // abs() ensures we don't get negative positions from crc32
        return abs(crc32($input)) % $this->bucketSize;
    }
}
