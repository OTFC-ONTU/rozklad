<?php

namespace App\Schedule;

use App\Enums\ConflictSeverity;

final readonly class Conflict
{
    public function __construct(
        public ConflictSeverity $severity,
        public string $code,
        public string $message,
        // Пара, з якою стався конфлікт, якщо вона є.
        public ?int $lessonId = null,
    ) {}

    public function isHard(): bool
    {
        return $this->severity === ConflictSeverity::Hard;
    }
}
