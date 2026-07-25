<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class MaxVideoDuration implements ValidationRule
{
    public function __construct(private readonly int $maxSeconds = 15)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$value instanceof UploadedFile) {
            return;
        }

        if (!str_starts_with((string) $value->getMimeType(), 'video/')) {
            return;
        }

        $getID3 = new \getID3();
        $info = $getID3->analyze($value->getRealPath());
        $duration = $info['playtime_seconds'] ?? null;

        if ($duration !== null && $duration > $this->maxSeconds) {
            $fail("El video no puede durar más de {$this->maxSeconds} segundos.");
        }
    }
}
