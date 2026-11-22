<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** Значення клітинки в матриці обмежень викладача. */
enum ConstraintLevel: string implements HasLabel
{
    /** «Не може» — жорстка заборона. */
    case Forbidden = 'forbidden';

    /** «Небажано» — лише попередження. */
    case Undesirable = 'undesirable';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::Forbidden => 'Не може',
            self::Undesirable => 'Небажано',
        };
    }
}
