<?php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class BdPhoneNumber implements Rule
{
    public function passes($attribute, $value)
    {
        // বাংলাদেশি ফোন নম্বর রেগুলার এক্সপ্রেশন (০১৮/০১৭/০১৯ ইত্যাদি ১৩/১১ ডিজিট)
        return preg_match('/^(?:\+88|88)?(01[3-9]\d{8})$/', $value);
    }

    public function message()
    {
        return 'The :attribute must be a valid Bangladeshi phone number.';
    }
}