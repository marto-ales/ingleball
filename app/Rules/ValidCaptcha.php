<?php

namespace App\Rules;

use App\Support\Captcha;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Session;

class ValidCaptcha implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (Session::get(Captcha::SESSION_KEY) !== (string) $value) {
            $fail('El captcha es incorrecto, generá uno nuevo.');
        }
    }
}
