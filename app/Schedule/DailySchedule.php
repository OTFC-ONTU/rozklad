<?php

namespace App\Schedule;

use App\Calendar\AcademicCalendar;
use App\Enums\DayLessonStatus;
use App\Enums\Weekday;
use App\Enums\WeekParity;
use App\Models\Lesson;
use App\Models\LessonParticipant;
use App\Models\ScheduleChange;
use App\Models\ScheduleChangeParticipant;
use App\Models\ScheduleVersion;
use App\Models\ShiftSetting;
use App\Models\StudentGroup;
use App\Models\Teacher;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Пари на конкретну дату: опублікована версія «діє з», тип тижня, свята, дні «за розкладом
 * іншого дня», стан групи (практика, сесія…) і заміни на дату.
 */
class DailySchedule
{
    private const array LESSON_RELATIONS = [
        'discipline', 'teachers', 'rooms.building', 'participants.group', 'participants.subgroup',
    ];

    public function __construct(private readonly AcademicCalendar $calendar) {}

    public function plan(CarbonInterface $date): DayPlan
    {
        $day = $this->calendar->day($date);
        $date = $day->date;
        $version = ScheduleVersion::effectiveOn($date);

        $shifts = ShiftSetting::for($date);
        $groups = StudentGroup::all();
        $states = $this->calendar->groupStatesOn($groups, $date);
        $groups = $groups
            ->filter(fn (StudentGroup $g) => $day->hasShift($shifts->scheduledShift($g->course)) && $states[$g->id]->hasClasses())
            ->keyBy('id');

        $template = ($day->isStudyDay && $version)
            ? Lesson::query()
                ->with(self::LESSON_RELATIONS)
                ->where('schedule_version_id', $version->id)
                ->where('weekday', $day->scheduleWeekday)
                ->whereIn('week', array_filter([WeekParity::Every, $day->parity]))
                ->get()
                // Потік лишається, якщо заняття є хоча б в однієї з його груп.
                ->filter(fn (Lesson $l) => $l->participants->contains(fn ($p) => $groups->has($p->student_group_id)))
                ->keyBy('id')
            : new EloquentCollection;

        $changes = ScheduleChange::query()
            ->with(['discipline', 'teachers', 'rooms.building', 'participants.group', 'participants.subgroup'])
            ->whereDate('date', $date)
            ->get();

        $byLesson = $changes->whereNotNull('lesson_id')->keyBy('lesson_id');
        $lessons = collect();

        foreach ($template as $lesson) {
            $change = $byLesson->get($lesson->id);

            $lessons->push(match (true) {
                $change === null => new DayLesson($lesson, DayLessonStatus::Regular, $lesson),
                $change->is_cancelled => new DayLesson($lesson, DayLessonStatus::Cancelled, $lesson, $change),
                default => new DayLesson($this->fromChange($change, $lesson, $day->scheduleWeekday), DayLessonStatus::Changed, $lesson, $change),
            });
        }

        // Додаткові пари з'являються лише в навчальний день.
        if ($day->isStudyDay) {
            foreach ($changes->whereNull('lesson_id') as $change) {
                $lessons->push(new DayLesson($this->fromChange($change, null, $day->scheduleWeekday), DayLessonStatus::Added, null, $change));
            }
        }

        $stale = $changes->whereNotNull('lesson_id')->reject(fn (ScheduleChange $c) => $template->has($c->lesson_id))->values();

        return new DayPlan(
            $day,
            $version,
            $lessons->sortBy(fn (DayLesson $l) => [$l->pair(), $l->lesson->participants->first()?->group?->code])->values(),
            $groups,
            $stale,
            $shifts,
        );
    }

    /** @return Collection<int, DayLesson> пари групи разом зі скасованими */
    public function forGroup(StudentGroup $group, CarbonInterface $date): Collection
    {
        return $this->plan($date)->forGroup($group->id);
    }

    /** @return Collection<int, DayLesson> пари викладача разом зі скасованими */
    public function forTeacher(Teacher $teacher, CarbonInterface $date): Collection
    {
        return $this->plan($date)->forTeacher($teacher->id);
    }

    /** Незбережена пара зі стану заміни — щоб усі перевірки й види працювали з однією моделлю. */
    private function fromChange(ScheduleChange $change, ?Lesson $original, ?Weekday $weekday): Lesson
    {
        $lesson = new Lesson([
            'schedule_version_id' => $original?->schedule_version_id,
            'weekday' => $original?->weekday ?? $weekday,
            'pair' => $change->pair,
            'week' => $original?->week ?? WeekParity::Every,
            'discipline_id' => $change->discipline_id,
            'title' => $change->title,
            'is_fixed_block' => $original?->is_fixed_block ?? false,
            'format' => $change->format,
            'online_url' => $change->online_url,
        ]);

        $lesson->setRelation('discipline', $change->discipline);
        $lesson->setRelation('teachers', $change->teachers);
        $lesson->setRelation('rooms', $change->rooms);
        $lesson->setRelation('participants', new EloquentCollection($change->participants->map(
            function (ScheduleChangeParticipant $p) {
                $participant = new LessonParticipant(['student_group_id' => $p->student_group_id, 'subgroup_id' => $p->subgroup_id]);
                $participant->setRelation('group', $p->group);
                $participant->setRelation('subgroup', $p->subgroup);

                return $participant;
            }
        )->all()));

        return $lesson;
    }
}
