<?php

namespace App\Schedule;

use App\Calendar\AcademicCalendar;
use App\Enums\WeekParity;
use App\Models\Lesson;
use App\Models\ScheduleVersion;
use App\Models\StudentGroup;
use App\Models\Teacher;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Пари на конкретну дату з опублікованого шаблону: враховує версію «діє з», тип тижня,
 * свята, дні «за розкладом іншого дня» і стан групи. Заміни на дату з'являться пізніше.
 */
class DailySchedule
{
    public function __construct(private readonly AcademicCalendar $calendar) {}

    /** @return Collection<int, Lesson> */
    public function forGroup(StudentGroup $group, CarbonInterface $date): Collection
    {
        if (! $this->calendar->groupStateOn($group, $date)->hasClasses()) {
            return new Collection;
        }

        return $this->query($date)?->whereHas('participants', fn ($q) => $q->where('student_group_id', $group->id))->get()
            ?? new Collection;
    }

    /** @return Collection<int, Lesson> лише пари, у групах яких цього дня є заняття */
    public function forTeacher(Teacher $teacher, CarbonInterface $date): Collection
    {
        $lessons = $this->query($date)?->whereHas('teachers', fn ($q) => $q->whereKey($teacher->id))->get()
            ?? new Collection;

        return $lessons->filter(fn (Lesson $l) => $l->participants->contains(
            fn ($p) => $this->calendar->groupStateOn($p->group, $date)->hasClasses()
        ))->values();
    }

    private function query(CarbonInterface $date): ?Builder
    {
        $day = $this->calendar->day($date);
        $version = ScheduleVersion::effectiveOn($date);

        if (! $day->isStudyDay || $version === null) {
            return null;
        }

        return Lesson::query()
            ->with(['discipline', 'teachers', 'rooms.building', 'participants.group', 'participants.subgroup'])
            ->where('schedule_version_id', $version->id)
            ->where('weekday', $day->scheduleWeekday)
            ->whereIn('week', array_filter([WeekParity::Every, $day->parity]))
            ->orderBy('pair');
    }
}
