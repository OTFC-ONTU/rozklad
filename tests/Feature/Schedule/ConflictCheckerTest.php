<?php

namespace Tests\Feature\Schedule;

use App\Enums\ConstraintLevel;
use App\Enums\LessonFormat;
use App\Enums\RoomType;
use App\Enums\Weekday;
use App\Enums\WeekParity;
use App\Models\Discipline;
use App\Models\Room;
use App\Models\ScheduleVersion;
use App\Models\ShiftSetting;
use App\Models\StudentGroup;
use App\Models\Subgroup;
use App\Models\Teacher;
use App\Schedule\ConflictChecker;
use App\Schedule\LessonDraft;
use App\Schedule\ScheduleConflictException;
use App\Schedule\ScheduleEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConflictCheckerTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleVersion $version;

    private Discipline $discipline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->version = ScheduleVersion::factory()->create(['effective_from' => '2026-09-01']);
        $this->discipline = Discipline::factory()->create();
    }

    private function draft(array $overrides = []): LessonDraft
    {
        return new LessonDraft(...array_merge([
            'versionId' => $this->version->id,
            'weekday' => Weekday::Monday,
            'pair' => 1,
            'disciplineId' => $this->discipline->id,
        ], $overrides));
    }

    private function save(array $overrides): void
    {
        app(ScheduleEditor::class)->save($this->draft($overrides));
    }

    /** @return list<string> */
    private function codes(array $overrides): array
    {
        return app(ConflictChecker::class)->check($this->draft($overrides))->codes();
    }

    public function test_free_slot_has_no_conflicts(): void
    {
        $group = StudentGroup::factory()->create();

        $this->assertSame([], $this->codes(['participants' => [[$group->id, null]]]));
    }

    public function test_group_cannot_have_two_lessons_at_once(): void
    {
        $group = StudentGroup::factory()->create();
        $this->save(['participants' => [[$group->id, null]]]);

        $this->assertContains('group_busy', $this->codes(['participants' => [[$group->id, null]]]));
    }

    public function test_numerator_and_denominator_do_not_clash(): void
    {
        $group = StudentGroup::factory()->create();
        $teacher = Teacher::factory()->create();
        $this->save(['participants' => [[$group->id, null]], 'teacherIds' => [$teacher->id], 'week' => WeekParity::Numerator]);

        $this->assertSame([], $this->codes([
            'participants' => [[$group->id, null]], 'teacherIds' => [$teacher->id], 'week' => WeekParity::Denominator,
        ]));
        $this->assertContains('group_busy', $this->codes([
            'participants' => [[$group->id, null]], 'week' => WeekParity::Every,
        ]));
    }

    public function test_subgroups_of_one_division_can_study_in_parallel(): void
    {
        $group = StudentGroup::factory()->create();
        [$first, $second] = Subgroup::factory()->count(2)->for($group, 'group')->create(['division' => 'Англійська']);
        $other = Subgroup::factory()->for($group, 'group')->create(['division' => 'Інформатика']);

        $this->save(['participants' => [[$group->id, $first->id]]]);

        $this->assertSame([], $this->codes(['participants' => [[$group->id, $second->id]]]));
        $this->assertContains('group_busy', $this->codes(['participants' => [[$group->id, $first->id]]]));
        $this->assertContains('group_busy', $this->codes(['participants' => [[$group->id, $other->id]]]));
        $this->assertContains('group_busy', $this->codes(['participants' => [[$group->id, null]]]));
    }

    public function test_teacher_cannot_be_in_two_places(): void
    {
        $teacher = Teacher::factory()->create();
        [$a, $b] = StudentGroup::factory()->count(2)->create();
        $this->save(['participants' => [[$a->id, null]], 'teacherIds' => [$teacher->id]]);

        $this->assertContains('teacher_busy', $this->codes(['participants' => [[$b->id, null]], 'teacherIds' => [$teacher->id]]));
    }

    public function test_stream_is_one_lesson_for_several_groups(): void
    {
        $teacher = Teacher::factory()->create();
        [$a, $b] = StudentGroup::factory()->count(2)->create();

        $this->assertSame([], $this->codes([
            'participants' => [[$a->id, null], [$b->id, null]], 'teacherIds' => [$teacher->id],
        ]));
    }

    public function test_room_is_exclusive_but_gym_takes_several_groups(): void
    {
        $room = Room::factory()->create();
        $gym = Room::factory()->gym()->create(['max_groups' => 2]);
        [$a, $b, $c] = StudentGroup::factory()->count(3)->create();

        $this->save(['participants' => [[$a->id, null]], 'roomIds' => [$room->id]]);
        $this->assertContains('room_busy', $this->codes(['participants' => [[$b->id, null]], 'roomIds' => [$room->id]]));

        $this->save(['participants' => [[$a->id, null]], 'roomIds' => [$gym->id], 'pair' => 2]);
        $this->save(['participants' => [[$b->id, null]], 'roomIds' => [$gym->id], 'pair' => 2]);
        $this->assertContains('room_overbooked', $this->codes(['participants' => [[$c->id, null]], 'roomIds' => [$gym->id], 'pair' => 2]));
    }

    public function test_room_type_and_capacity(): void
    {
        $group = StudentGroup::factory()->create(['students_count' => 32]);
        $computer = Discipline::factory()->create(['room_type' => RoomType::ComputerLab]);
        $small = Room::factory()->create(['capacity' => 20]);

        $codes = $this->codes(['participants' => [[$group->id, null]], 'roomIds' => [$small->id], 'disciplineId' => $computer->id]);

        $this->assertContains('room_type', $codes);
        $this->assertContains('room_capacity', $codes);
    }

    public function test_remote_lesson_skips_room_checks(): void
    {
        $room = Room::factory()->create();
        [$a, $b] = StudentGroup::factory()->count(2)->create();
        $this->save(['participants' => [[$a->id, null]], 'roomIds' => [$room->id]]);

        $this->assertSame([], $this->codes([
            'participants' => [[$b->id, null]], 'roomIds' => [$room->id], 'format' => LessonFormat::Remote,
        ]));
    }

    public function test_fourth_pair_follows_settings_of_course_shift(): void
    {
        $group = StudentGroup::factory()->create(['course' => 3]);
        ShiftSetting::create(['effective_from' => '2026-09-01', 'second_shift_courses' => [3], 'second_fourth_pair_enabled' => false]);
        ShiftSetting::create(['effective_from' => '2026-08-01', 'second_shift_courses' => [3]]);

        $this->assertContains('slot_disabled', $this->codes(['participants' => [[$group->id, null]], 'pair' => 4]),
            'Діє найновіший запис на дату версії, а не будь-який.');
    }

    public function test_template_is_monday_to_friday(): void
    {
        $group = StudentGroup::factory()->create();

        $this->assertContains('invalid_weekday', $this->codes(['participants' => [[$group->id, null]], 'weekday' => Weekday::Saturday]));
    }

    public function test_other_courses_use_first_shift_settings(): void
    {
        $group = StudentGroup::factory()->create(['course' => 1]);
        ShiftSetting::create(['effective_from' => '2026-01-01', 'second_shift_courses' => [3], 'second_fourth_pair_enabled' => false]);

        $this->assertNotContains('slot_disabled', $this->codes(['participants' => [[$group->id, null]], 'pair' => 4]));
    }

    public function test_template_keeps_scheduled_shift_when_second_shift_is_off(): void
    {
        $group = StudentGroup::factory()->create(['course' => 3]);
        ShiftSetting::create([
            'effective_from' => '2026-01-01', 'second_shift_courses' => [3], 'second_shift_enabled' => false,
            'second_fourth_pair_enabled' => false,
        ]);

        $this->assertContains('slot_disabled', $this->codes(['participants' => [[$group->id, null]], 'pair' => 4]),
            'Вимкнення 2 зміни не змінює тижневий розклад.');
    }

    public function test_fixed_block_occupies_the_slot(): void
    {
        $group = StudentGroup::factory()->create();
        $this->save([
            'participants' => [[$group->id, null]], 'weekday' => Weekday::Wednesday, 'pair' => 4,
            'disciplineId' => null, 'title' => 'Класна година', 'isFixedBlock' => true,
        ]);

        $this->assertContains('fixed_block', $this->codes([
            'participants' => [[$group->id, null]], 'weekday' => Weekday::Wednesday, 'pair' => 4,
        ]));
    }

    public function test_teacher_constraints(): void
    {
        $teacher = Teacher::factory()->create();
        $group = StudentGroup::factory()->create();
        $teacher->constraints()->createMany([
            ['weekday' => Weekday::Monday, 'pair' => 1, 'level' => ConstraintLevel::Forbidden],
            ['weekday' => Weekday::Tuesday, 'pair' => null, 'level' => ConstraintLevel::Undesirable],
            ['weekday' => Weekday::Wednesday, 'pair' => 1, 'level' => ConstraintLevel::Forbidden, 'week' => WeekParity::Numerator],
            ['weekday' => Weekday::Thursday, 'pair' => 1, 'level' => ConstraintLevel::Forbidden, 'starts_on' => '2026-10-01', 'ends_on' => '2026-10-14'],
            ['weekday' => Weekday::Friday, 'pair' => 1, 'level' => ConstraintLevel::Forbidden, 'starts_on' => '2026-06-01', 'ends_on' => '2026-06-30'],
        ]);
        $base = ['participants' => [[$group->id, null]], 'teacherIds' => [$teacher->id]];

        $this->assertContains('teacher_forbidden', $this->codes($base));
        $this->assertSame(['teacher_undesirable'], $this->codes([...$base, 'weekday' => Weekday::Tuesday, 'pair' => 3]));
        $this->assertSame([], $this->codes([...$base, 'weekday' => Weekday::Wednesday, 'week' => WeekParity::Denominator]));
        $this->assertSame(['teacher_constraint_upcoming'], $this->codes([...$base, 'weekday' => Weekday::Thursday]));
        $this->assertSame([], $this->codes([...$base, 'weekday' => Weekday::Friday]));
    }

    public function test_same_discipline_twice_a_day_is_a_warning(): void
    {
        $group = StudentGroup::factory()->create();
        $this->save(['participants' => [[$group->id, null]]]);

        $report = app(ConflictChecker::class)->check($this->draft(['participants' => [[$group->id, null]], 'pair' => 2]));

        $this->assertSame(['discipline_twice'], $report->codes());
        $this->assertFalse($report->hasHard());
    }

    public function test_editing_a_lesson_does_not_conflict_with_itself(): void
    {
        $group = StudentGroup::factory()->create();
        $lesson = app(ScheduleEditor::class)->save($this->draft(['participants' => [[$group->id, null]]]));

        $moved = app(ScheduleEditor::class)->save(LessonDraft::fromLesson($lesson)->with(pair: 3));

        $this->assertSame(3, $moved->pair);
        $this->assertSame(1, $this->version->lessons()->count());
    }

    public function test_hard_conflict_blocks_saving_unless_reason_given(): void
    {
        $group = StudentGroup::factory()->create();
        $this->save(['participants' => [[$group->id, null]]]);
        $draft = $this->draft(['participants' => [[$group->id, null]]]);

        try {
            app(ScheduleEditor::class)->save($draft);
            $this->fail('Очікувався ScheduleConflictException.');
        } catch (ScheduleConflictException $e) {
            $this->assertSame(['group_busy'], $e->report->codes());
        }

        $lesson = app(ScheduleEditor::class)->save($draft, 'Тимчасово, до перерозподілу аудиторій');
        $this->assertSame('Тимчасово, до перерозподілу аудиторій', $lesson->override_reason);
    }

    public function test_subgroup_must_belong_to_group(): void
    {
        [$a, $b] = StudentGroup::factory()->count(2)->create();
        $foreign = Subgroup::factory()->for($b, 'group')->create();

        $this->assertSame(['subgroup_mismatch'], $this->codes(['participants' => [[$a->id, $foreign->id]]]));
    }
}
