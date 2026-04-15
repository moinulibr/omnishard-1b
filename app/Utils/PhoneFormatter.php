<?php

namespace App\Utils;

class PhoneFormatter
{
    /**
     * Normalize BD Phone numbers to standard 01XXXXXXXXX format.
     */
    public static function normalize(string $phone): string
    {
        // Remove all non-numeric characters like +, -, spaces
        $number = preg_replace('/[^0-9]/', '', $phone);

        // Handle +8801, 8801, 008801 cases
        if (str_starts_with($number, '880')) {
            $number = substr($number, 2);
        } elseif (str_starts_with($number, '00880')) {
            $number = substr($number, 4);
        }

        return $number; // returns 01XXXXXXXXX
    }

    public static function isValidBDPhone(string $phone): bool
    {
        return preg_match('/^(01)[3-9][0-9]{8}$/', $phone);
    }
    
}


/* class PhoneFormatter
{
    public static function normalize(string $phone): string
    {
        // সব নন-ডিজিট ক্যারেক্টার রিমুভ করা
        $number = preg_replace('/[^0-9]/', '', $phone);

        // যদি ১১ ডিজিট হয় (যেমন: 017...) তবে সামনে 88 যোগ করা
        if (strlen($number) == 11) {
            $number = '88' . $number;
        }

        return $number;
    }
} */
