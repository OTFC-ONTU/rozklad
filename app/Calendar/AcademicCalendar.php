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

        $isSaturday = $date->dayOfWeekIso === 6;

        if ($special?->kind === CalendarDayKind::Working) {
            // Робоча субота без указаного дня — за чергуванням субот; змін не відмічено — навчаються всі.
            $weekday = $special->follows_weekday
                ?? ($isSaturday ? $semester->saturdayWeekday($date) : Weekday::tryFrom($date->dayOfWeekIso));
            $shifts = $isSaturday ? ($semester->saturdayShifts($date) ?: null) : null;

            return new CalendarDate(
                $date, $semester, $weekday !== null, $weekday, $special->follows_parity ?? $parity,
                isRescheduled: $isSaturday || $special->follows_weekday !== null || $special->follows_parity !== null,
                note: $special->note,
                shifts: $shifts,
            );
        }

        // Субота навчальна лише для змін, відмічених у семестрі; йде за розкладом дня з чергування.
        if ($isSaturday) {
            $shifts = $semester->saturdayShifts($date);

            return $shifts === []
                ? new CalendarDate($date, $semester, false, null, $parity)
                : new CalendarDate($date, $semester, true, $semester->saturdayWeekday($date), $parity, isRescheduled: true, shifts: $shifts);
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

    /**
     * Стан кожної групи на дату одним запитом.
     *
     * @param  iterable<StudentGroup>  $groups
     * @return array<int, GroupActivityKind> id групи => стан
     */
    public function groupStatesOn(iterable $groups, CarbonInterface $date): array
    {
        $activities = GroupActivity::query()
            ->whereDate('starts_on', '<=', $date)
            ->whereDate('ends_on', '>=', $date)
            ->get()
            ->keyBy('student_group_id');
        $default = $this->semesterFor($date) ? GroupActivityKind::Theory : GroupActivityKind::Vacation;

        $states = [];
        foreach ($groups as $group) {
            $states[$group->id] = $activities->get($group->id)?->kind ?? $default;
        }

        return $states;
    }

    /** Стан групи на дату: явний запис графіка, інакше теорія в семестрі й канікули поза ним. */
    public function groupStateOn(StudentGroup $group, CarbonInterface $date): GroupActivityKind
    {
        return $this->activityOn($group, $date)?->kind
            ?? ($this->semesterFor($date) ? GroupActivityKind::Theory : GroupActivityKind::Vacation);
    }
}
