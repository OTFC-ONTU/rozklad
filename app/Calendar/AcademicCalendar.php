<?php

namespace App\Calendar;

use App\Enums\CalendarDayKind;
use App\Enums\GroupActivityKind;
use App\Enums\Weekday;
use App\Models\CalendarDay;
use App\Models\GroupActivity;
use App\Models\Semester;
use App\Models\StudentGroup;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Календар навчального процесу: семестри, чисельник/знаменник, свята,
 * дні «за розкладом іншого дня» і стан груп на дату.
 */
class AcademicCalendar
{
    /** @var Collection<int, Semester>|null */
    private ?Collection $semesters = null;

    public function semesterFor(CarbonInterface $date): ?Semester
    {
        $this->semesters ??= Semester::with(['parityOverrides', 'academicYear'])->orderBy('starts_on')->get();

        return $this->semesters->first(fn (Semester $s) => $s->contains($date));
    }

    public function day(CarbonInterface $date): CalendarDate
    {
        $date = CarbonImmutable::parse($date)->startOfDay();
        $semester = $this->semesterFor($date);
        $parity = $semester?->parityOn($date);
        $special = CalendarDay::whereDate('date', $date)->first();

        if ($semester === null || $special?->kind === CalendarDayKind::Holiday) {
            return new CalendarDate($date, $semester, false, null, $parity, note: $special?->note);
        }

        if ($special?->kind === CalendarDayKind::Working) {
            $weekday = $special->follows_weekday ?? Weekday::tryFrom($date->dayOfWeekIso);

            return new CalendarDate(
                $date, $semester, $weekday !== null, $weekday, $special->follows_parity ?? $parity,
                isRescheduled: $special->follows_weekday !== null || $special->follows_parity !== null,
                note: $special->note,
            );
        }

        $weekday = Weekday::tryFrom($date->dayOfWeekIso); // неділя → null

        return new CalendarDate($date, $semester, $weekday !== null, $weekday, $parity);
    }

    /** Запис графіка, що покриває дату, якщо він є. */
    public function activityOn(StudentGroup $group, CarbonInterface $date): ?GroupActivity
    {
        return $group->activities()
            ->with('practiceType')
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->first();
    }

    /** Стан групи на дату: явний запис графіка, інакше теорія в семестрі й канікули поза ним. */
    public function groupStateOn(StudentGroup $group, CarbonInterface $date): GroupActivityKind
    {
        return $this->activityOn($group, $date)?->kind
            ?? ($this->semesterFor($date) ? GroupActivityKind::Theory : GroupActivityKind::Vacation);
    }
}
