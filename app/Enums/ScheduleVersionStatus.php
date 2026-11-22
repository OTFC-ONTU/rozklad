<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ScheduleVersionStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Чернетка',
            self::Published => 'Опубліковано',
        };
    }
}
