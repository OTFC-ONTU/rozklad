<?php

namespace App\Enums;

enum ConflictSeverity: string
{
    /** Блокує збереження; обійти можна лише з причиною. */
    case Hard = 'hard';

    case Warning = 'warning';
}
