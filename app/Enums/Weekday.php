<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Розклад складається на Пн–Пт; навчальна субота йде за розкладом одного з цих днів
 * (див. Semester::saturdayWeekday). Субота лишається як календарний день.
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

    /** @return list<self> дні, на які складається розклад */
    public static function schoolDays(): array
    {
        return [self::Monday, self::Tuesday, self::Wednesday, self::Thursday, self::Friday];
    }

    /** @return array<int, string> Пн–Пт для вибору у формах */
    public static function schoolDayOptions(): array
    {
        return collect(self::schoolDays())->mapWithKeys(fn (self $d) => [$d->value => $d->label()])->all();
    }

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
