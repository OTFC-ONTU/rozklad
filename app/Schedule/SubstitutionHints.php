<?php

namespace App\Schedule;

use App\Enums\ConstraintLevel;
use App\Enums\Shift;
use App\Enums\WeekParity;
use App\Models\Discipline;
use App\Models\Lesson;
use App\Models\StudentGroup;
use App\Models\Teacher;
use Illuminate\Support\Collection;

/**
 * Підказки після скасування: які групи лишилися без пари і хто з їхніх викладачів вільний.
 */
class SubstitutionHints
{
    /**
     * Групи зміни, у яких пару скасовано і нічого не поставлено натомість.
     *
     * @return Collection<int, array{group: StudentGroup, pair: int, cancelled: DayLesson}>
     */
    public function idleGroups(DayPlan $plan, Shift $shift): Collection
    {
        $active = $plan->active();

        return $plan->inShift($shift)
            ->filter->isCancelled()
            ->flatMap(fn (DayLesson $cancelled) => $cancelled->lesson->participants
                ->pluck('group')
                ->unique('id')
                ->filter(fn (StudentGroup $g) => $plan->groups->has($g->id))
                ->reject(fn (StudentGroup $g) => $active->contains(
                    fn (DayLesson $l) => $l->pair() === $cancelled->pair() && $l->hasGroup($g->id)
                ))
                ->map(fn (StudentGroup $g) => ['group' => $g, 'pair' => $cancelled->pair(), 'cancelled' => $cancelled]))
            ->unique(fn (array $i) => "{$i['group']->id}-{$i['pair']}")
            ->sortBy(fn (array $i) => [$i['pair'], $i['group']->code])
            ->values();
    }

    /**
     * Вільні в цю пару викладачі, які ведуть групу за чинним шаблоном, з їхніми дисциплінами в цій групі.
     * Викладачі скасованої пари не пропонуються — найімовірніше, їх немає.
     *
     * @return Collection<int, array{teacher: Teacher, disciplines: Collection<int, Discipline>}>
     */
    public function candidates(DayPlan $plan, int $groupId, int $pair, ?DayLesson $cancelled = null): Collection
    {
        if ($plan->version === null || ! $plan->day->isStudyDay) {
            return collect();
        }

        $busy = $plan->active()
            ->filter(fn (DayLesson $l) => $l->pair() === $pair)
            ->flatMap->teacherIds()
            ->merge($cancelled?->teacherIds() ?? [])
            ->flip();

        $teaching = Lesson::query()
            ->with(['teachers.constraints', 'discipline'])
            ->where('schedule_version_id', $plan->version->id)
            ->whereNotNull('discipline_id')
            ->whereHas('participants', fn ($q) => $q->where('student_group_id', $groupId))
            ->get();

        $weekday = $plan->day->scheduleWeekday;
        $week = $plan->day->parity ?? WeekParity::Every;

        return $teaching
            ->flatMap(fn (Lesson $l) => $l->teachers->map(fn (Teacher $t) => ['teacher' => $t, 'discipline' => $l->discipline]))
            ->reject(fn (array $i) => $busy->has($i['teacher']->id))
            ->reject(fn (array $i) => $i['teacher']->constraints->contains(
                fn ($c) => $c->level === ConstraintLevel::Forbidden
                    && $c->activeOn($plan->day->date)
                    && $c->covers($weekday, $pair, $week)
            ))
            ->groupBy(fn (array $i) => $i['teacher']->id)
            ->map(fn (Collection $items) => [
                'teacher' => $items->first()['teacher'],
                'disciplines' => $items->pluck('discipline')->unique('id')->values(),
            ])
            ->sortBy(fn (array $i) => $i['teacher']->short_name)
            ->values();
    }
}
