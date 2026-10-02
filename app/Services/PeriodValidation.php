<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class PeriodValidation
{
    public function validate(array $data, array $existing = []): void
    {
        Validator::make(array_replace($existing, Arr::only($data, ['starts_at', 'ends_at'])), [
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ])->validate();
    }
}
