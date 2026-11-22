<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RoomType: string implements HasLabel
{
    case Classroom = 'classroom';
    case ComputerLab = 'computer_lab';
    case Laboratory = 'laboratory';
    case Gym = 'gym';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::Classroom => 'Навчальна аудиторія',
            self::ComputerLab => 'Комп\'ютерний клас',
            self::Laboratory => 'Лабораторія',
            self::Gym => 'Спортзал',
        };
    }
}
