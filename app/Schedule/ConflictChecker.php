<?php

namespace App\Schedule;

use App\Calendar\AcademicCalendar;
use App\Enums\ConstraintLevel;
use App\Enums\LessonFormat;
use App\Enums\WeekParity;
use App\Models\Discipline;
use App\Models\Lesson;
use App\Models\LessonParticipant;
use App\Models\Room;
use App\Models\ScheduleVersion;
use App\Models\SlotSetting;
use App\Models\StudentGroup;
use App\Models\Subgroup;
use App\Models\Teacher;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Перевіряє пару тижневого шаблону на конфлікти з рештою версії розкладу,
 * а заміну на дату — з рештою пар цього дня (і станом груп за графіком).
 * Жорсткі конфлікти блокують збереження, попередження — ні.
 *
 * Ще не перевіряється (потребує практик і навантаження):
 * група на практиці в шаблоні, практики як зайнятість, відхилення від навантаження,
 * «вікна», ліміти пар викладача на день, переїзд між корпусами.
 */
class ConflictChecker
{
    private const array LESSON_RELATIONS = [
        'discipline', 'teachers', 'rooms.building',
        'participants.group', 'participants.subgroup',
    ];

    /** Перевірка пари тижневого шаблону проти решти версії. */
    public function check(LessonDraft $draft): ConflictReport
    {
        $version = ScheduleVersion::findOrFail($draft->versionId);

        $sameDay = Lesson::query()
            ->with(self::LESSON_RELATIONS)
            ->where('schedule_version_id', $draft->versionId)
            ->where('weekday', $draft->weekday)
            ->whereIn('week', $draft->week->overlapping())
            ->when($draft->lessonId, fn ($q, $id) => $q->whereKeyNot($id))
            ->get();

        return $this->run($draft, $sameDay, $version->effective_from, null);
    }

    /**
     * Перевірка пари на конкретну дату (заміна, додаткова пара) проти всіх пар цього дня.
     * День тижня і тип тижня чернетки беруться з календаря: «за розкладом іншого дня» враховано.
     *
     * @param  ?DayLesson  $editing  пара дня, яку редагують: вона не конфліктує сама з собою
     */
    public function checkOnDate(LessonDraft $draft, DayPlan $plan, ?DayLesson $editing = null): ConflictReport
    {
        $day = $plan->day;

        if (! $day->isStudyDay) {
            $report = new ConflictReport;
            $report->hard('day_off', 'Цього дня занять немає.');

            return $report;
        }

        $draft = $draft->with(weekday: $day->scheduleWeekday, week: $day->parity ?? WeekParity::Every);
        $sameDay = new EloquentCollection($plan->active()
            ->reject(fn (DayLesson $l) => $editing !== null && $l->key() === $editing->key())
            ->map(fn (DayLesson $l) => $l->lesson)
            ->all());

        return $this->run($draft, $sameDay, $day->date, $plan);
    }

    /**
     * @param  EloquentCollection<int, Lesson>  $sameDay  інші пари того самого дня, що можуть збігтися за тижнем
     * @param  ?DayPlan  $plan  заданий для перевірки на конкретну дату
     */
    private function run(LessonDraft $draft, EloquentCollection $sameDay, CarbonInterface $date, ?DayPlan $plan): ConflictReport
    {
        $report = new ConflictReport;

        $participants = $this->participants($draft);
        $teachers = Teacher::with('constraints')->findMany($draft->teacherIds);
        $rooms = Room::with('building')->findMany($draft->roomIds);
        $discipline = $draft->disciplineId ? Discipline::find($draft->disciplineId) : null;

        if (! $this->checkStructure($draft, $participants, $report)) {
            return $report;
        }

        $concurrent = $sameDay->filter(fn (Lesson $l) => $l->pair === $draft->pair)->values();

        if ($plan !== null) {
            $this->checkGroupsStudy($participants, $plan, $report);
        }

        $this->checkSlots($draft, $participants, $date, $report);
        $this->checkGroups($participants, $concurrent, $report);
        $this->checkTeachers($teachers, $concurrent, $report);
        $this->checkTeacherConstraints($draft, $teachers, $date, $plan !== null, $report);

        if ($draft->format === LessonFormat::InPerson) {
            $this->checkRooms($participants, $rooms, $concurrent, $report);
            $this->checkRoomSuitability($discipline, $rooms, $report);
        }

        $this->checkSameDisciplineTwice($draft, $participants, $sameDay, $report);

        return $report;
    }

    /**
     * @return Collection<int, LessonParticipant> незбережені моделі з підвантаженими group/subgroup
     */
    private function participants(LessonDraft $draft): Collection
    {
        $groups = StudentGroup::with('shiftChanges')
            ->findMany(array_column($draft->participants, 0))
            ->keyBy('id');
        $subgroups = Subgroup::findMany(array_filter(array_column($draft->participants, 1)))->keyBy('id');

        return collect($draft->participants)
            ->filter(fn (array $p) => $groups->has($p[0]))
            ->map(function (array $p) use ($groups, $subgroups) {
                $participant = new LessonParticipant(['student_group_id' => $p[0], 'subgroup_id' => $p[1]]);
                $participant->setRelation('group', $groups[$p[0]]);
                $participant->setRelation('subgroup', $p[1] ? $subgroups->get($p[1]) : null);

                return $participant;
            })
            ->values();
    }

    /** Базова цілісність; якщо вона порушена, решту перевірок робити немає сенсу. */
    private function checkStructure(LessonDraft $draft, Collection $participants, ConflictReport $report): bool
    {
        if ($draft->pair < 1 || $draft->pair > SlotSetting::MAX_PAIRS) {
            $report->hard('invalid_pair', 'Номер пари має бути від 1 до '.SlotSetting::MAX_PAIRS.'.');
        }

        if ($participants->isEmpty()) {
            $report->hard('no_participants', 'Не вказано групу або підгрупу.');
        }

        foreach ($participants as $p) {
            if ($p->subgroup_id !== null && $p->subgroup?->student_group_id !== $p->student_group_id) {
                $report->hard('subgroup_mismatch', "Підгрупа не належить групі {$p->group->code}.");
            }
        }

        if ($draft->disciplineId === null && blank($draft->title)) {
            $report->hard('no_discipline', 'Не вказано дисципліну.');
        }

        return ! $report->hasHard();
    }

    /** На дату: група на практиці, сесії, канікулах тощо. */
    private function checkGroupsStudy(Collection $participants, DayPlan $plan, ConflictReport $report): void
    {
        $calendar = app(AcademicCalendar::class);

        foreach ($participants->pluck('group')->unique('id') as $group) {
            if (! $plan->groups->has($group->id)) {
                $state = $calendar->groupStateOn($group, $plan->day->date);
                $report->hard('group_not_studying', "{$group->code} цього дня: ".mb_strtolower($state->label()).'.');
            }
        }
    }

    private function checkSlots(LessonDraft $draft, Collection $participants, CarbonInterface $date, ConflictReport $report): void
    {
        $participants
            ->map(fn (LessonParticipant $p) => $p->group)
            ->unique('id')
            ->groupBy(fn (StudentGroup $g) => $g->shiftOn($date)->value)
            ->each(function (Collection $groups) use ($draft, $date, $report) {
                $shift = $groups->first()->shiftOn($date);

                if (! SlotSetting::for($shift, $date)->allows($draft->weekday, $draft->pair)) {
                    $report->hard('slot_disabled', sprintf(
                        '%s, %d пара вимкнені для %s (%s).',
                        $draft->weekday->label(), $draft->pair, mb_strtolower($shift->label()), $groups->pluck('code')->implode(', '),
                    ));
                }
            });
    }

    private function checkGroups(Collection $participants, EloquentCollection $concurrent, ConflictReport $report): void
    {
        foreach ($concurrent as $other) {
            foreach ($participants as $mine) {
                $clash = $other->participants->first(fn (LessonParticipant $p) => $mine->overlapsWith($p));

                if ($clash === null) {
                    continue;
                }

                if ($other->is_fixed_block) {
                    $report->hard('fixed_block', "У {$mine->label()} у цей час «{$other->displayTitle()}».", $other->id);
                } else {
                    $report->hard('group_busy', "{$mine->label()} уже має пару «{$other->displayTitle()}» ({$clash->label()}, {$other->week->label()}).", $other->id);
                }

                break;
            }
        }
    }

    private function checkTeachers(EloquentCollection $teachers, EloquentCollection $concurrent, ConflictReport $report): void
    {
        foreach ($concurrent as $other) {
            foreach ($other->teachers->intersect($teachers) as $teacher) {
                $groups = $other->participants->map->label()->implode(', ');
                $report->hard('teacher_busy', "{$teacher->short_name} у цей час веде «{$other->displayTitle()}» у {$groups}.", $other->id);
            }
        }
    }

    /**
     * @param  bool  $onDate  перевірка конкретної дати: тимчасове обмеження діє лише у свої дати
     */
    private function checkTeacherConstraints(LessonDraft $draft, EloquentCollection $teachers, CarbonInterface $date, bool $onDate, ConflictReport $report): void
    {
        foreach ($teachers as $teacher) {
            foreach ($teacher->constraints as $constraint) {
                if (! $constraint->covers($draft->weekday, $draft->pair, $draft->week)) {
                    continue;
                }

                if ($onDate && ! $constraint->activeOn($date)) {
                    continue;
                }

                // Тимчасове обмеження, що вже закінчилося до початку дії версії, не враховуємо.
                if ($constraint->ends_on !== null && $constraint->ends_on->lt($date)) {
                    continue;
                }

                $note = $constraint->note ? " ({$constraint->note})" : '';

                // Тимчасове обмеження, що почнеться пізніше, стосується лише частини періоду
                // дії шаблону — це питання для замін на дату, тому лише попереджаємо.
                if ($constraint->starts_on !== null && $constraint->starts_on->gt($date)) {
                    $report->warning('teacher_constraint_upcoming', sprintf(
                        '%s: «%s» з %s%s.',
                        $teacher->short_name, mb_strtolower($constraint->level->label()), $constraint->starts_on->format('d.m.Y'), $note,
                    ));

                    continue;
                }

                if ($constraint->level === ConstraintLevel::Forbidden) {
                    $report->hard('teacher_forbidden', "{$teacher->short_name} не може в цей час{$note}.");
                } else {
                    $report->warning('teacher_undesirable', "{$teacher->short_name}: цей час небажаний{$note}.");
                }
            }
        }
    }

    private function checkRooms(Collection $participants, EloquentCollection $rooms, EloquentCollection $concurrent, ConflictReport $report): void
    {
        foreach ($rooms as $room) {
            $occupants = $concurrent->filter(fn (Lesson $l) => $l->rooms->contains($room));

            if ($room->max_groups <= 1) {
                foreach ($occupants as $other) {
                    $report->hard('room_busy', "Аудиторія {$room->label()} зайнята: «{$other->displayTitle()}», {$other->participants->map->label()->implode(', ')}.", $other->id);
                }
            } elseif ($occupants->isNotEmpty()) {
                $groupsCount = $participants->count() + $occupants->sum(fn (Lesson $l) => $l->participants->count());

                if ($groupsCount > $room->max_groups) {
                    $report->hard('room_overbooked', "В аудиторії {$room->label()} одночасно буде {$groupsCount} груп(и) при максимумі {$room->max_groups}.");
                }
            }

            // Місткість рахуємо лише тоді, коли пара в одній аудиторії: інакше невідомо, хто де.
            if ($room->capacity !== null && $rooms->count() === 1) {
                $students = $participants->sum(fn (LessonParticipant $p) => $p->studentsCount() ?? 0);

                if ($room->max_groups > 1) {
                    $students += $occupants->sum(fn (Lesson $l) => $l->participants->sum(fn ($p) => $p->studentsCount() ?? 0));
                }

                if ($students > $room->capacity) {
                    $report->hard('room_capacity', "Аудиторія {$room->label()} на {$room->capacity} місць, а здобувачів {$students}.");
                }
            }
        }
    }

    private function checkRoomSuitability(?Discipline $discipline, EloquentCollection $rooms, ConflictReport $report): void
    {
        if ($discipline?->room_type === null || $rooms->isEmpty()) {
            return;
        }

        if (! $rooms->contains(fn (Room $r) => $r->type === $discipline->room_type)) {
            $report->hard('room_type', "«{$discipline->short_name}» потребує: ".mb_strtolower($discipline->room_type->label()).'.');
        }
    }

    private function checkSameDisciplineTwice(LessonDraft $draft, Collection $participants, EloquentCollection $sameDay, ConflictReport $report): void
    {
        if ($draft->disciplineId === null) {
            return;
        }

        $others = $sameDay->filter(fn (Lesson $l) => $l->pair !== $draft->pair && $l->discipline_id === $draft->disciplineId);

        foreach ($others as $other) {
            $mine = $participants->first(
                fn (LessonParticipant $m) => $other->participants->contains(fn ($p) => $m->overlapsWith($p))
            );

            if ($mine !== null) {
                $report->warning('discipline_twice', "У {$mine->label()} «{$other->displayTitle()}» уже є цього дня ({$other->pair} пара).", $other->id);
            }
        }
    }
}
