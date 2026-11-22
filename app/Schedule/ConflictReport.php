<?php

namespace App\Schedule;

use App\Enums\ConflictSeverity;
use Illuminate\Support\Collection;

final class ConflictReport
{
    /** @var list<Conflict> */
    private array $conflicts = [];

    public function hard(string $code, string $message, ?int $lessonId = null): void
    {
        $this->conflicts[] = new Conflict(ConflictSeverity::Hard, $code, $message, $lessonId);
    }

    public function warning(string $code, string $message, ?int $lessonId = null): void
    {
        $this->conflicts[] = new Conflict(ConflictSeverity::Warning, $code, $message, $lessonId);
    }

    /** @return Collection<int, Conflict> */
    public function all(): Collection
    {
        return collect($this->conflicts);
    }

    /** @return Collection<int, Conflict> */
    public function hardConflicts(): Collection
    {
        return $this->all()->filter->isHard()->values();
    }

    /** @return Collection<int, Conflict> */
    public function warnings(): Collection
    {
        return $this->all()->reject->isHard()->values();
    }

    public function hasHard(): bool
    {
        return $this->hardConflicts()->isNotEmpty();
    }

    public function isEmpty(): bool
    {
        return $this->conflicts === [];
    }

    /** @return list<string> */
    public function codes(): array
    {
        return $this->all()->pluck('code')->unique()->values()->all();
    }
}
