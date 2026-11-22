<?php

namespace Tests\Feature\Schedule;

use App\Enums\CalendarDayKind;
use App\Enums\ConstraintLevel;
use App\Enums\DayLessonStatus;
use App\Enums\GroupActivityKind;
use App\Enums\LessonFormat;
use App\Enums\ScheduleVersionStatus;
use App\Enums\Weekday;
use App\Enums\WeekParity;
use App\Models\AcademicYear;
use App\Models\CalendarDay;
use App\Models\Discipline;
use App\Models\Lesson;
use App\Models\ScheduleChange;
use App\Models\ScheduleVersion;
use App\Models\StudentGroup;
use App\Models\Teacher;
use App\Schedule\ConflictChecker;
use App\Schedule\DailySchedule;
use App\Schedule\DayEditor;
use App\Schedule\DayLesson;
use App\Schedule\DayPlan;
use App\Schedule\LessonDraft;
use App\Schedule\ScheduleConflictException;
use App\Schedule\ScheduleEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyChangesTest extends TestCase
{
    use RefreshDatabase;

    // Понеділок, чисельник (тиждень з 31.08 — чисельник).
    private const string MONDAY = '2026-11-23';

    private ScheduleVersion $version;

    private StudentGroup $a;

    private StudentGroup $b;

    private Teacher $ivanov;

    private Teacher $petrova;

    private Discipline $math;

    private Lesson $mathA;

    protected function setUp(): void
    {
        parent::setUp();

        $year = AcademicYear::create(['title' => '2026–2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30']);
        $year->semesters()->create(['number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-31', 'first_week_parity' => WeekParity::Numerator]);

        $this->version = ScheduleVersion::factory()->create(['effective_from' => '2026-09-01', 'status' => ScheduleVersionStatus::Published]);
        [$this->a, $this->b] = StudentGroup::factory()->count(2)->sequence(['code' => '2КБ-06'], ['code' => '4КГ-10'])->create();
        $this->ivanov = Teacher::factory()->create(['short_name' => 'Іванов І.І.']);
        $this->petrova = Teacher::factory()->create(['short_name' => 'Петрова П.П.']);
        $this->math = Discipline::factory()->create(['short_name' => 'Математика']);

        $this->mathA = $this->lesson(Weekday::Monday, 1, $this->a, $this->ivanov, $this->math);
        $this->lesson(Weekday::Monday, 1, $this->b, $this->petrova);
        $this->lesson(Weekday::Monday, 2, $this->a, $this->petrova);
    }

    private function lesson(Weekday $day, int $pair, StudentGroup $group, Teacher $teacher, ?Discipline $discipline = null, WeekParity $week = WeekParity::Every): Lesson
    {
        return app(ScheduleEditor::class)->save(new LessonDraft(
            versionId: $this->version->id, weekday: $day, pair: $pair, week: $week,
            disciplineId: ($discipline ?? Discipline::factory()->create())->id,
            teacherIds: [$teacher->id], participants: [[$group->id, null]],
        ));
    }

    private function plan(string $date = self::MONDAY): DayPlan
    {
        return app(DailySchedule::class)->plan(CarbonImmutable::parse($date));
    }

    private function editor(): DayEditor
    {
        return app(DayEditor::class);
    }

    private function draftFor(DayLesson $lesson, mixed ...$changes): LessonDraft
    {
        return LessonDraft::fromLesson($lesson->lesson)->with(...[...$changes, 'lessonId' => null]);
    }

    private function newDraft(array $values): LessonDraft
    {
        return new LessonDraft(...[
            'versionId' => $this->version->id, 'weekday' => Weekday::Monday, 'pair' => 1,
            'disciplineId' => $this->math->id, ...$values,
        ]);
    }

    public function test_plan_follows_template(): void
    {
        $plan = $this->plan();

        $this->assertSame([1, 1, 2], $plan->lessons->map->pair()->all());
        $this->assertTrue($plan->lessons->every(fn (DayLesson $l) => $l->status === DayLessonStatus::Regular));
        $this->assertSame([1, 2], $plan->forGroup($this->a->id)->map->pair()->all());
    }

    public function test_cancel_and_restore(): void
    {
        $plan = $this->plan();
        $this->editor()->cancel($plan, $plan->find("l{$this->mathA->id}"), 'Лікарняний');

        $plan = $this->plan();
        $lesson = $plan->find("l{$this->mathA->id}");
        $this->assertSame(DayLessonStatus::Cancelled, $lesson->status);
        $this->assertSame('Лікарняний', $lesson->reason());
        $this->assertSame([2], $plan->active()->filter->hasGroup($this->a->id)->map->pair()->values()->all());

        // Наступного понеділка пара на місці: заміна лише на дату.
        $this->assertSame(DayLessonStatus::Regular, $this->plan('2026-11-30')->find("l{$this->mathA->id}")->status);

        $this->editor()->restore($lesson);
        $this->assertSame(DayLessonStatus::Regular, $this->plan()->find("l{$this->mathA->id}")->status);
        $this->assertSame(0, ScheduleChange::count());
    }

    public function test_replace_teacher_checks_conflicts(): void
    {
        $plan = $this->plan();
        $lesson = $plan->find("l{$this->mathA->id}");

        try {
            $this->editor()->save($plan, $this->draftFor($lesson, teacherIds: [$this->petrova->id]), $lesson);
            $this->fail('Петрова в цей час веде пару в 4КГ-10.');
        } catch (ScheduleConflictException $e) {
            $this->assertContains('teacher_busy', $e->report->codes());
        }

        $free = Teacher::factory()->create();
        $this->editor()->save($plan, $this->draftFor($lesson, teacherIds: [$free->id]), $lesson, 'Відрядження');

        $changed = $this->plan()->find("l{$this->mathA->id}");
        $this->assertSame(DayLessonStatus::Changed, $changed->status);
        $this->assertSame(['teachers'], $changed->changedParts());
        $this->assertSame([$free->id], $changed->teacherIds());
        $this->assertCount(1, $this->plan()->forTeacher($free->id));
        $this->assertCount(0, $this->plan('2026-11-30')->forTeacher($free->id));
    }

    public function test_saving_template_state_removes_change(): void
    {
        $plan = $this->plan();
        $lesson = $plan->find("l{$this->mathA->id}");
        $this->editor()->save($plan, $this->draftFor($lesson, pair: 3), $lesson);
        $this->assertSame(3, $this->plan()->find("l{$this->mathA->id}")->pair());

        $plan = $this->plan();
        $moved = $plan->find("l{$this->mathA->id}");
        $this->assertNull($this->editor()->save($plan, $this->draftFor($moved, pair: 1), $moved));
        $this->assertSame(0, ScheduleChange::count());
    }

    public function test_added_lesson_after_cancellation(): void
    {
        $plan = $this->plan();
        $this->editor()->cancel($plan, $plan->find("l{$this->mathA->id}"));

        $plan = $this->plan();
        $free = Teacher::factory()->create();
        $draft = $this->newDraft(['teacherIds' => [$free->id], 'participants' => [[$this->a->id, null]]]);
        $this->assertSame([], app(ConflictChecker::class)->checkOnDate($draft, $plan)->codes());

        $this->editor()->save($plan, $draft);
        $added = $this->plan()->lessons->firstWhere('status', DayLessonStatus::Added);
        $this->assertNotNull($added);
        $this->assertTrue($added->hasGroup($this->a->id));
        $this->assertStringStartsWith('c', $added->key());

        // Група 4КГ-10 уже має першу пару.
        $busy = $this->newDraft(['teacherIds' => [$free->id], 'participants' => [[$this->b->id, null]], 'pair' => 1]);
        $this->assertContains('group_busy', app(ConflictChecker::class)->checkOnDate($busy, $this->plan())->codes());

        // Скасування додаткової пари — видалення.
        $plan = $this->plan();
        $this->editor()->cancel($plan, $plan->find($added->key()));
        $this->assertSame(1, ScheduleChange::count());
    }

    public function test_temporary_teacher_constraint_applies_only_on_its_dates(): void
    {
        $this->ivanov->constraints()->create([
            'weekday' => Weekday::Monday, 'pair' => 3, 'level' => ConstraintLevel::Forbidden,
            'starts_on' => '2026-11-23', 'ends_on' => '2026-11-27', 'note' => 'Курси',
        ]);
        $draft = $this->newDraft(['pair' => 3, 'teacherIds' => [$this->ivanov->id], 'participants' => [[$this->b->id, null]]]);
        $checker = app(ConflictChecker::class);

        $this->assertContains('teacher_forbidden', $checker->checkOnDate($draft, $this->plan())->codes());
        $this->assertNotContains('teacher_forbidden', $checker->checkOnDate($draft, $this->plan('2026-11-30'))->codes());
    }

    public function test_group_on_practice_and_day_off(): void
    {
        $this->b->activities()->create(['kind' => GroupActivityKind::Practice, 'starts_on' => '2026-11-23', 'ends_on' => '2026-11-29']);
        $checker = app(ConflictChecker::class);

        $plan = $this->plan();
        $this->assertCount(0, $plan->forGroup($this->b->id));
        $draft = $this->newDraft(['pair' => 3, 'teacherIds' => [$this->petrova->id], 'participants' => [[$this->b->id, null]]]);
        $this->assertContains('group_not_studying', $checker->checkOnDate($draft, $plan)->codes());

        CalendarDay::create(['date' => '2026-11-24', 'kind' => CalendarDayKind::Holiday]);
        $this->assertSame(['day_off'], $checker->checkOnDate($draft, $this->plan('2026-11-24'))->codes());
    }

    public function test_rescheduled_day_uses_other_weekday(): void
    {
        $tuesday = $this->lesson(Weekday::Tuesday, 2, $this->a, $this->ivanov, week: WeekParity::Denominator);
        CalendarDay::create(['date' => '2026-11-21', 'kind' => CalendarDayKind::Working, 'follows_weekday' => Weekday::Tuesday, 'follows_parity' => WeekParity::Denominator]);

        $plan = $this->plan('2026-11-21');
        $this->assertSame([$tuesday->id], $plan->lessons->map(fn ($l) => $l->original->id)->all());

        $lesson = $plan->lessons->sole();
        $this->editor()->save($plan, $this->draftFor($lesson, format: LessonFormat::Remote, roomIds: []), $lesson);
        $this->assertSame(['format'], $this->plan('2026-11-21')->lessons->sole()->changedParts());
    }

    public function test_changes_of_replaced_version_become_stale(): void
    {
        $plan = $this->plan();
        $this->editor()->cancel($plan, $plan->find("l{$this->mathA->id}"));

        $this->version->duplicate('Нова', '2026-11-16')->publish();

        $plan = $this->plan();
        $this->assertCount(1, $plan->staleChanges);
        $this->assertFalse($plan->lessons->contains->isCancelled());
    }
}
