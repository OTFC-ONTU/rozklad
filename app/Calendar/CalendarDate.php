<?php

namespace App\Calendar;

use App\Enums\Weekday;
use App\Enums\WeekParity;
use App\Models\Semester;
use Carbon\CarbonImmutable;

/** Що відбувається в навчальному закладі в конкретну дату. */
final readonly class CalendarDate
{
    public function __construct(
        public CarbonImmutable $date,
        public ?Semester $semester,
        // Чи йдуть цього дня пари (у межах семестру, не свято, не неділя).
        public bool $isStudyDay,
        // За розкладом якого дня тижня йдуть пари; може відрізнятися від календарного.
        public ?Weekday $scheduleWeekday,
        // Тип тижня для розкладу (з урахуванням «за розкладом іншого дня»).
        public ?WeekParity $parity,
        public bool $isRescheduled = false,
        public ?string $note = null,
    ) {}

    /** «Субота 21.11.2026 — за розкладом вівторка, знаменник». */
    public function describe(): string
    {
        $text = mb_convert_case($this->date->locale('uk')->dayName, MB_CASE_TITLE).' '.$this->date->format('d.m.Y');

        if (! $this->isStudyDay) {
            return $text.' — занять немає'.($this->note ? " ({$this->note})" : '');
        }

        if ($this->isRescheduled) {
            $text .= ' — за розкладом: '.mb_strtolower($this->scheduleWeekday->label());
        }

        return $text.($this->parity ? ', '.mb_strtolower($this->parity->label()) : '');
    }
}
