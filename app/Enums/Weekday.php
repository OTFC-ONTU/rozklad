<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Навчальні дні: Пн–Пт завжди, субота — якщо увімкнена для зміни.
 * Значення збігаються з ISO-8601 (Carbon::dayOfWeekIso).
 */
enum Weekday: int implements HasLabel
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::Monday => 'Понеділок',
            self::Tuesday => 'Вівторок',
            self::Wednesday => 'Середа',
            self::Thursday => 'Четвер',
            self::Friday => 'П\'ятниця',
            self::Saturday => 'Субота',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Monday => 'Пн',
            self::Tuesday => 'Вт',
            self::Wednesday => 'Ср',
            self::Thursday => 'Чт',
            self::Friday => 'Пт',
            self::Saturday => 'Сб',
        };
    }
}
