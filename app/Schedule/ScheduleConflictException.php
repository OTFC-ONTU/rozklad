<?php

namespace App\Schedule;

use RuntimeException;

/** Пару не збережено: є жорсткі конфлікти, а причину обходу не вказано. */
class ScheduleConflictException extends RuntimeException
{
    public function __construct(public readonly ConflictReport $report)
    {
        parent::__construct(
            'Пару не збережено через конфлікти: '.$report->hardConflicts()->pluck('message')->implode('; ')
        );
    }
}
