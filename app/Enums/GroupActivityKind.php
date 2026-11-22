<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/** Стан групи в графіку освітнього процесу. */
enum GroupActivityKind: string implements HasColor, HasLabel
{
    case Theory = 'theory';
    case Practice = 'practice';
    case CreditWeek = 'credit_week';
    case Session = 'session';
    case Thesis = 'thesis';
    case Exam = 'exam';
    case Vacation = 'vacation';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::Theory => 'Теоретичне навчання',
            self::Practice => 'Практика',
            self::CreditWeek => 'Заліковий тиждень',
            self::Session => 'Сесія',
            self::Thesis => 'ВКР',
            self::Exam => 'ЗНО / НМТ',
            self::Vacation => 'Канікули',
        };
    }

    /** Позначка в клітинці графіка. */
    public function abbreviation(): string
    {
        return match ($this) {
            self::Theory => '',
            self::Practice => 'П',
            self::CreditWeek => 'ЗТ',
            self::Session => 'С',
            self::Thesis => 'ВКР',
            self::Exam => 'ЗНО',
            self::Vacation => 'К',
        };
    }

    /** Колір у графіку; для практики береться колір виду практики. */
    public function hex(): string
    {
        return match ($this) {
            self::Theory => '#ffffff',
            self::Practice => '#60a5fa',
            self::CreditWeek => '#fde68a',
            self::Session => '#fca5a5',
            self::Thesis => '#c4b5fd',
            self::Exam => '#fdba74',
            self::Vacation => '#86efac',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Theory => 'gray',
            self::Practice => 'info',
            self::CreditWeek, self::Exam => 'warning',
            self::Session => 'danger',
            self::Thesis => 'primary',
            self::Vacation => 'success',
        };
    }

    /** Чи йдуть у групи звичайні пари за шаблоном. */
    public function hasClasses(): bool
    {
        return $this === self::Theory || $this === self::CreditWeek;
    }
}
