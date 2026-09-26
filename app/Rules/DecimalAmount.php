<?php

namespace App\Rules;

use App\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class DecimalAmount implements ValidationRule
{
    public function __construct(private int $minimum = 1) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            if (Money::cents($value) < $this->minimum) {
                $fail('El monto mínimo es '.Money::format($this->minimum).'.');
            }
        } catch (\InvalidArgumentException $e) {
            $fail($e->getMessage());
        }
    }
}
