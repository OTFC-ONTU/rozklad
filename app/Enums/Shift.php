<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Shift: int implements HasLabel
{
    case First = 1;
    case Second = 2;

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return "{$this->value} зміна";
    }
}
