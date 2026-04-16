<?php
namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class BdPhoneNumber implements Rule
{
    public function passes($attribute, $value)
    {
        return preg_match('/^(?:\+88|88)?(01[3-9]\d{8})$/', $value);
    }

    public function message()
    {
        return 'The :attribute must be a valid Bangladeshi phone number.';
    }
}