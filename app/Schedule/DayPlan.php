<?php

namespace App\Schedule;

use App\Calendar\CalendarDate;
use App\Enums\Shift;
use App\Models\ScheduleChange;
use App\Models\ScheduleVersion;
use App\Models\ShiftSetting;
use App\Models\StudentGroup;
use Illuminate\Support\Collection;

/** Усі пари навчального закладу на дату з урахуванням замін. */
final readonly class DayPlan
{
    /**
     * @param  Collection<int, DayLesson>  $lessons  разом зі скасованими, відсортовані за парою
     * @param  Collection<int, StudentGroup>  $groups  групи, у яких цього дня є заняття, з id як ключем
     * @param  Collection<int, ScheduleChange>  $staleChanges  заміни пар, яких уже немає в шаблоні на цей день
     * @param  ShiftSetting  $shifts  налаштування змін, що діють цього дня
     */
    public function __construct(
        public CalendarDate $day,
        public ?ScheduleVersion $version,
        public Collection $lessons,
        public Collection $groups,
        public Collection $staleChanges,
        public ShiftSetting $shifts,
    ) {}

    /** @return Collection<int, DayLesson> пари, що відбуваються (без скасованих) */
    public function active(): Collection
    {
        return $this->lessons->reject->isCancelled()->values();
    }

    public function find(string $key): ?DayLesson
    {
        return $this->lessons->first(fn (DayLesson $l) => $l->key() === $key);
    }

    /** @return Collection<int, DayLesson> */
    public function forGroup(int $groupId): Collection
    {
        return $this->lessons->filter(fn (DayLesson $l) => $l->hasGroup($groupId))->values();
    }

    /** @return Collection<int, DayLesson> */
    public function forTeacher(int $teacherId): Collection
    {
        return $this->lessons->filter(fn (DayLesson $l) => $l->hasTeacher($teacherId))->values();
    }

    /** Фактична зміна групи цього дня (з урахуванням вимкненої 2 зміни). */
    public function shiftOfGroup(StudentGroup $group): Shift
    {
        return $this->shifts->shiftOf($group->course);
    }

    /** Зміна пари — за першою групою (потоки складають з груп однієї зміни). */
    public function shiftOf(DayLesson $lesson): ?Shift
    {
        $group = $lesson->lesson->participants->first()?->group;

        return $group ? $this->shiftOfGroup($group) : null;
    }

    /** @return list<Shift> зміни, що працюють цього дня (субота може бути лише для однієї) */
    public function workingShifts(): array
    {
        $shifts = array_values(array_filter(
            $this->shifts->activeShifts(),
            fn (Shift $s) => $this->groups->contains(fn (StudentGroup $g) => $this->shiftOfGroup($g) === $s),
        ));

        return $shifts ?: $this->shifts->activeShifts();
    }

    /** @return Collection<int, DayLesson> */
    public function inShift(Shift $shift): Collection
    {
        return $this->lessons->filter(fn (DayLesson $l) => $this->shiftOf($l) === $shift)->values();
    }

    /** @return Collection<int, StudentGroup> групи зміни, у яких цього дня є заняття */
    public function groupsInShift(Shift $shift): Collection
    {
        return $this->groups->filter(fn (StudentGroup $g) => $this->shiftOfGroup($g) === $shift)->sortBy('code')->values();
    }
}
