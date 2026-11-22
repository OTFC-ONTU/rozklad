<?php

namespace Tests\Feature\Filament;

use App\Enums\DayLessonStatus;
use App\Enums\ScheduleVersionStatus;
use App\Enums\Weekday;
use App\Enums\WeekParity;
use App\Filament\Pages\DailyChanges;
use App\Models\AcademicYear;
use App\Models\Discipline;
use App\Models\Lesson;
use App\Models\Room;
use App\Models\ScheduleChange;
use App\Models\ScheduleVersion;
use App\Models\StudentGroup;
use App\Models\Teacher;
use App\Models\User;
use App\Schedule\DailySchedule;
use App\Schedule\LessonDraft;
use App\Schedule\ScheduleEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DailyChangesPageTest extends TestCase
{
    use RefreshDatabase;

    private const string MONDAY = '2026-11-23';

    private StudentGroup $group;

    private StudentGroup $other;

    private Teacher $teacher;

    private Teacher $colleague;

    private Discipline $os;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $year = AcademicYear::create(['title' => '2026–2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30']);
        $year->semesters()->create(['number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-31', 'first_week_parity' => WeekParity::Numerator]);
        $version = ScheduleVersion::factory()->create(['effective_from' => '2026-09-01', 'status' => ScheduleVersionStatus::Published]);

        $this->group = StudentGroup::factory()->create(['code' => '4КГ-10']);
        $this->other = StudentGroup::factory()->create(['code' => '4КГ-12']);
        $this->teacher = Teacher::factory()->create(['short_name' => 'Кривченко Ю.В.']);
        $this->colleague = Teacher::factory()->create(['short_name' => 'Шувалова І.О.']);
        $this->os = Discipline::factory()->create(['short_name' => 'Опер. системи']);
        $room = Room::factory()->create();

        $editor = app(ScheduleEditor::class);
        $this->lesson = $editor->save(new LessonDraft(
            versionId: $version->id, weekday: Weekday::Monday, pair: 2, disciplineId: $this->os->id,
            teacherIds: [$this->teacher->id], roomIds: [$room->id], participants: [[$this->group->id, null]],
        ));
        // Шувалова веде 4КГ-10 в інший день — тому її пропонуємо на заміну.
        $editor->save(new LessonDraft(
            versionId: $version->id, weekday: Weekday::Tuesday, pair: 1, disciplineId: Discipline::factory()->create(['short_name' => 'Ін. мова'])->id,
            teacherIds: [$this->colleague->id], participants: [[$this->group->id, null]],
        ));
        $editor->save(new LessonDraft(
            versionId: $version->id, weekday: Weekday::Monday, pair: 1, disciplineId: $this->os->id,
            teacherIds: [$this->colleague->id], participants: [[$this->other->id, null]],
        ));
    }

    private function page()
    {
        return Livewire::test(DailyChanges::class, ['date' => self::MONDAY, 'shift' => 1]);
    }

    private function lessonStatus(): DayLessonStatus
    {
        return app(DailySchedule::class)->plan(CarbonImmutable::parse(self::MONDAY))->find("l{$this->lesson->id}")->status;
    }

    public function test_page_renders_teachers_of_the_day(): void
    {
        $this->get(DailyChanges::getUrl(['date' => self::MONDAY]))->assertOk()
            ->assertSee('Кривченко Ю.В.')
            ->assertSee('4КГ-10')
            ->assertSee('чисельник');
    }

    public function test_cancel_suggest_and_substitute(): void
    {
        $page = $this->page()
            ->callAction('cancelLesson', ['reason' => 'Лікарняний'], ['key' => "l{$this->lesson->id}"])
            ->assertHasNoActionErrors();

        $this->assertSame(DayLessonStatus::Cancelled, $this->lessonStatus());

        // Підказка: 4КГ-10 без 2-ї пари, Шувалова вільна і веде цю групу.
        $page->assertSee('Групи без пари')->assertSee('Шувалова І.О. · Ін. мова');

        $page->mountAction('addLesson', ['teacher' => $this->colleague->id, 'group' => $this->group->id, 'pair' => 2, 'discipline' => $this->os->id])
            ->assertSchemaStateSet(['pair' => 2, 'groups' => [$this->group->id], 'teachers' => [$this->colleague->id]])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $added = ScheduleChange::whereNull('lesson_id')->sole();
        $this->assertSame([$this->colleague->id], $added->teachers->modelKeys());
        $page->assertDontSee('Групи без пари');
    }

    public function test_edit_replaces_teacher_and_restore(): void
    {
        $substitute = Teacher::factory()->create();

        $this->page()
            ->mountAction('editLesson', ['key' => "l{$this->lesson->id}"])
            ->assertSchemaStateSet(['pair' => 2, 'teachers' => [$this->teacher->id]])
            ->fillForm(['teachers' => [$substitute->id], 'reason' => 'Відрядження'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame(DayLessonStatus::Changed, $this->lessonStatus());
        $this->assertSame('Відрядження', ScheduleChange::sole()->reason);

        $this->page()->callAction('restoreLesson', arguments: ['key' => "l{$this->lesson->id}"]);
        $this->assertSame(DayLessonStatus::Regular, $this->lessonStatus());
    }

    public function test_cancel_from_edit_modal(): void
    {
        $this->page()
            ->mountAction('editLesson', ['key' => "l{$this->lesson->id}"])
            ->mountAction('cancelFromEdit')
            ->callMountedAction();

        $this->assertSame(DayLessonStatus::Cancelled, $this->lessonStatus());

        $this->page()
            ->mountAction('editLesson', ['key' => "l{$this->lesson->id}"])
            ->mountAction('restoreFromEdit')
            ->callMountedAction();

        $this->assertSame(DayLessonStatus::Regular, $this->lessonStatus());
    }

    public function test_conflicting_substitution_requires_reason(): void
    {
        // Шувалова в 1 пару веде 4КГ-12.
        $page = $this->page()
            ->mountAction('editLesson', ['key' => "l{$this->lesson->id}"])
            ->fillForm(['pair' => 1, 'teachers' => [$this->colleague->id]])
            ->assertFormFieldVisible('override_reason')
            ->callMountedAction()
            ->assertHasFormErrors(['override_reason' => 'required']);

        $this->assertSame(0, ScheduleChange::count());

        $page->fillForm(['override_reason' => 'Об\'єднали групи'])->callMountedAction()->assertHasNoFormErrors();
        $this->assertSame('Об\'єднали групи', ScheduleChange::sole()->override_reason);
    }

    public function test_print_sheets(): void
    {
        $this->page()->callAction('cancelLesson', ['reason' => null], ['key' => "l{$this->lesson->id}"]);

        $this->get(route('filament.admin.print.attendance', ['date' => self::MONDAY, 'shift' => 1]))
            ->assertOk()
            ->assertSee('Листок обліку виходу викладачів')
            ->assertSee('ЗАТВЕРДЖУЮ')
            ->assertSee('Кривченко Ю.В.')
            ->assertSee('class="cancelled"', false);

        $this->get(route('filament.admin.print.changes', ['date' => self::MONDAY, 'shift' => 1]))
            ->assertOk()
            ->assertSee('Зміни в розкладі занять на понеділок, 23.11.2026')
            ->assertSee('по чисельнику')
            ->assertSee('4КГ-10')
            ->assertDontSee('4КГ-12');

        $this->get(route('filament.admin.print.changes', ['date' => '2026-13-45', 'shift' => 1]))->assertNotFound();
    }

    public function test_print_requires_login(): void
    {
        auth()->logout();

        $this->get(route('filament.admin.print.changes', ['date' => self::MONDAY, 'shift' => 1]))->assertRedirect();
    }
}
