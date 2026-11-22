<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CalendarDayKind: string implements HasLabel
{
    /** Свято або неробочий день: занять немає. */
    case Holiday = 'holiday';

    /** Робочий день; можна вказати, за розкладом якого дня й тижня він іде. */
    case Working = 'working';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::Holiday => 'Свято / неробочий',
            self::Working => 'Робочий (перенесення)',
        };
    }
}
