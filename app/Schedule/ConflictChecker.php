<?php

namespace App\Schedule;

use App\Enums\ConstraintLevel;
use App\Enums\LessonFormat;
use App\Models\Discipline;
use App\Models\Lesson;
use App\Models\LessonParticipant;
use App\Models\Room;
use App\Models\ScheduleVersion;
use App\Models\SlotSetting;
use App\Models\StudentGroup;
use App\Models\Subgroup;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Перевіряє пару тижневого шаблону на конфлікти з рештою версії розкладу.
 * Жорсткі конфлікти блокують збереження, попередження — ні.
 *
 * Ще не перевіряється (потребує календаря, практик і навантаження):
 * група на практиці / сесії / канікулах, практики як зайнятість, відхилення від навантаження,
 * «вікна», ліміти пар викладача на день, переїзд між корпусами.
 */
class ConflictChecker
{
    private const array LESSON_RELATIONS = [
        'discipline', 'teachers', 'rooms.building',
        'participants.group', 'participants.subgroup',
    ];

    public function check(LessonDraft $draft): ConflictReport
    {
        $report = new ConflictReport;
        $version = ScheduleVersion::findOrFail($draft->versionId);

        $participants = $this->participants($draft);
        $teachers = Teacher::with('constraints')->findMany($draft->teacherIds);
        $rooms = Room::with('building')->findMany($draft->roomIds);
        $discipline = $draft->disciplineId ? Discipline::find($draft->disciplineId) : null;

        if (! $this->checkStructure($draft, $participants, $report)) {
            return $report;
        }

        $concurrent = $this->concurrentLessons($draft);

        $this->checkSlots($draft, $participants, $version, $report);
        $this->checkGroups($participants, $concurrent, $report);
        $this->checkTeachers($teachers, $concurrent, $report);
        $this->checkTeacherConstraints($draft, $teachers, $version, $report);

        if ($draft->format === LessonFormat::InPerson) {
            $this->checkRooms($participants, $rooms, $concurrent, $report);
            $this->checkRoomSuitability($discipline, $rooms, $report);
        }

        $this->checkSameDisciplineTwice($draft, $participants, $report);

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

    /** @return EloquentCollection<int, Lesson> пари версії в той самий слот, що можуть збігтися за тижнем */
    private function concurrentLessons(LessonDraft $draft): EloquentCollection
    {
        return Lesson::query()
            ->with(self::LESSON_RELATIONS)
            ->where('schedule_version_id', $draft->versionId)
            ->where('weekday', $draft->weekday)
            ->where('pair', $draft->pair)
            ->whereIn('week', $draft->week->overlapping())
            ->when($draft->lessonId, fn ($q, $id) => $q->whereKeyNot($id))
            ->get();
    }

    private function checkSlots(LessonDraft $draft, Collection $participants, ScheduleVersion $version, ConflictReport $report): void
    {
        $date = $version->effective_from;

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

    private function checkTeacherConstraints(LessonDraft $draft, EloquentCollection $teachers, ScheduleVersion $version, ConflictReport $report): void
    {
        $date = $version->effective_from;

        foreach ($teachers as $teacher) {
            foreach ($teacher->constraints as $constraint) {
                if (! $constraint->covers($draft->weekday, $draft->pair, $draft->week)) {
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

    private function checkSameDisciplineTwice(LessonDraft $draft, Collection $participants, ConflictReport $report): void
    {
        if ($draft->disciplineId === null) {
            return;
        }

        $sameDay = Lesson::query()
            ->with(['discipline', 'participants.group', 'participants.subgroup'])
            ->where('schedule_version_id', $draft->versionId)
            ->where('weekday', $draft->weekday)
            ->where('pair', '!=', $draft->pair)
            ->where('discipline_id', $draft->disciplineId)
            ->whereIn('week', $draft->week->overlapping())
            ->when($draft->lessonId, fn ($q, $id) => $q->whereKeyNot($id))
            ->get();

        foreach ($sameDay as $other) {
            $mine = $participants->first(
                fn (LessonParticipant $m) => $other->participants->contains(fn ($p) => $m->overlapsWith($p))
            );

            if ($mine !== null) {
                $report->warning('discipline_twice', "У {$mine->label()} «{$other->displayTitle()}» уже є цього дня ({$other->pair} пара).", $other->id);
            }
        }
    }
}
