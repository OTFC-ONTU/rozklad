<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum LessonFormat: string implements HasLabel
{
    case InPerson = 'in_person';
    case Remote = 'remote';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'Очно',
            self::Remote => 'Дистанційно',
        };
    }
}
