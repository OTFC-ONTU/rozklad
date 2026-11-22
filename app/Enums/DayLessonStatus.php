<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Що сталося з парою на конкретну дату порівняно з шаблоном. */
enum DayLessonStatus: string implements HasColor, HasLabel
{
    case Regular = 'regular';
    case Changed = 'changed';
    case Cancelled = 'cancelled';
    case Added = 'added';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::Regular => 'За розкладом',
            self::Changed => 'Заміна',
            self::Cancelled => 'Скасовано',
            self::Added => 'Додаткова пара',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Regular => 'gray',
            self::Changed, self::Added => 'warning',
            self::Cancelled => 'danger',
        };
    }
}
