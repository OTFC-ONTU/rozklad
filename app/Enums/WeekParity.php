<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Тиждень, у який проходить пара: щотижня, лише за чисельником або лише за знаменником.
 */
enum WeekParity: string implements HasLabel
{
    case Every = 'every';
    case Numerator = 'numerator';
    case Denominator = 'denominator';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::Every => 'Щотижня',
            self::Numerator => 'Чисельник',
            self::Denominator => 'Знаменник',
        };
    }

    /** Чи можуть дві пари з такими тижнями опинитися в одному календарному тижні. */
    public function overlaps(self $other): bool
    {
        return $this === self::Every || $other === self::Every || $this === $other;
    }

    /** @return list<self> усі значення, що перетинаються з цим */
    public function overlapping(): array
    {
        return array_values(array_filter(self::cases(), fn (self $case) => $this->overlaps($case)));
    }
}
