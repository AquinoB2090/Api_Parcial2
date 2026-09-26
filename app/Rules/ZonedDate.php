<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ZonedDate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            $fail('Use ISO 8601 con zona horaria, por ejemplo 2026-09-26T20:00:00Z.');

            return;
        }
        $parsed = date_parse($value);
        if ($parsed['error_count'] || $parsed['warning_count']) {
            $fail('La fecha no es válida.');

            return;
        }
        try {
            CarbonImmutable::parse($value);
        } catch (\Throwable) {
            $fail('La fecha no es válida.');
        }
    }
}
