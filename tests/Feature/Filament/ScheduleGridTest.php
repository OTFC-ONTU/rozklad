<?php

namespace Tests\Feature\Filament;

use App\Enums\Weekday;
use App\Enums\WeekParity;
use App\Filament\Pages\ScheduleGrid;
use App\Filament\Resources\Buildings\BuildingResource;
use App\Filament\Resources\Disciplines\DisciplineResource;
use App\Filament\Resources\Rooms\RoomResource;
use App\Filament\Resources\ScheduleVersions\ScheduleVersionResource;
use App\Filament\Resources\SlotSettings\SlotSettingResource;
use App\Filament\Resources\StudentGroups\StudentGroupResource;
use App\Filament\Resources\Teachers\TeacherResource;
use App\Models\Discipline;
use App\Models\Lesson;
use App\Models\Room;
use App\Models\ScheduleVersion;
use App\Models\StudentGroup;
use App\Models\Teacher;
use App\Models\User;
use App\Schedule\LessonDraft;
use App\Schedule\ScheduleEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ScheduleGridTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleVersion $version;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->version = ScheduleVersion::factory()->create();
    }

    public function test_admin_pages_render(): void
    {
        StudentGroup::factory()->create();
        Room::factory()->create();

        foreach ([
            BuildingResource::class, RoomResource::class, DisciplineResource::class, TeacherResource::class,
            StudentGroupResource::class, ScheduleVersionResource::class, SlotSettingResource::class,
        ] as $resource) {
            $this->get($resource::getUrl())->assertOk();
        }

        $this->get(ScheduleGrid::getUrl())->assertOk();
    }

    public function test_grid_shows_lessons_of_the_version(): void
    {
        $group = StudentGroup::factory()->create(['code' => '4КГ-10']);
        $discipline = Discipline::factory()->create(['short_name' => 'Опер. системи']);
        app(ScheduleEditor::class)->save(new LessonDraft(
            versionId: $this->version->id, weekday: Weekday::Monday, pair: 4, week: WeekParity::Numerator,
            disciplineId: $discipline->id, participants: [[$group->id, null]],
        ));

        Livewire::test(ScheduleGrid::class, ['version' => $this->version->id])
            ->assertSee('4КГ-10')
            ->assertSee('Опер. системи');
    }

    public function test_create_lesson_from_cell(): void
    {
        $group = StudentGroup::factory()->create();
        $discipline = Discipline::factory()->create();
        $teacher = Teacher::factory()->create();
        $room = Room::factory()->create();

        Livewire::test(ScheduleGrid::class, ['version' => $this->version->id])
            ->mountAction('createLesson', ['group' => $group->id, 'weekday' => 2, 'pair' => 3])
            ->assertFormFieldHidden('override_reason')
            ->assertSchemaStateSet(['weekday' => Weekday::Tuesday, 'pair' => 3, 'groups' => [$group->id]])
            ->fillForm(['discipline_id' => $discipline->id, 'teachers' => [$teacher->id], 'rooms' => [$room->id]])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $lesson = Lesson::with(['teachers', 'rooms', 'participants'])->sole();
        $this->assertSame(Weekday::Tuesday, $lesson->weekday);
        $this->assertSame(3, $lesson->pair);
        $this->assertSame([$teacher->id], $lesson->teachers->modelKeys());
        $this->assertSame([$room->id], $lesson->rooms->modelKeys());
        $this->assertSame($group->id, $lesson->participants->sole()->student_group_id);
    }

    public function test_conflict_requires_reason(): void
    {
        $teacher = Teacher::factory()->create();
        $discipline = Discipline::factory()->create();
        [$a, $b] = StudentGroup::factory()->count(2)->create();
        app(ScheduleEditor::class)->save(new LessonDraft(
            versionId: $this->version->id, weekday: Weekday::Monday, pair: 1,
            disciplineId: $discipline->id, teacherIds: [$teacher->id], participants: [[$a->id, null]],
        ));

        $page = Livewire::test(ScheduleGrid::class, ['version' => $this->version->id])
            ->mountAction('createLesson', ['group' => $b->id, 'weekday' => 1, 'pair' => 1])
            ->fillForm(['discipline_id' => $discipline->id, 'teachers' => [$teacher->id]])
            ->assertFormFieldVisible('override_reason')
            ->callMountedAction()
            ->assertHasFormErrors(['override_reason' => 'required']);

        $this->assertSame(1, Lesson::count());

        $page->fillForm(['override_reason' => 'Погоджено із заступником'])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $this->assertSame('Погоджено із заступником', Lesson::latest('id')->first()->override_reason);
    }

    public function test_edit_and_delete_lesson(): void
    {
        $group = StudentGroup::factory()->create();
        $lesson = app(ScheduleEditor::class)->save(new LessonDraft(
            versionId: $this->version->id, weekday: Weekday::Monday, pair: 1,
            disciplineId: Discipline::factory()->create()->id, participants: [[$group->id, null]],
        ));

        Livewire::test(ScheduleGrid::class, ['version' => $this->version->id])
            ->mountAction('editLesson', ['lesson' => $lesson->id])
            ->assertSchemaStateSet(['pair' => 1, 'groups' => [$group->id]])
            ->fillForm(['pair' => 2, 'week' => WeekParity::Denominator->value])
            ->callMountedAction()
            ->assertHasNoFormErrors();

        $lesson->refresh();
        $this->assertSame(2, $lesson->pair);
        $this->assertSame(WeekParity::Denominator, $lesson->week);

        Livewire::test(ScheduleGrid::class, ['version' => $this->version->id])
            ->mountAction('editLesson', ['lesson' => $lesson->id])
            ->mountAction('deleteLesson')
            ->callMountedAction();

        $this->assertModelMissing($lesson);
    }

    public function test_duplicate_version_copies_lessons(): void
    {
        $group = StudentGroup::factory()->create();
        $teacher = Teacher::factory()->create();
        app(ScheduleEditor::class)->save(new LessonDraft(
            versionId: $this->version->id, weekday: Weekday::Monday, pair: 1,
            disciplineId: Discipline::factory()->create()->id, teacherIds: [$teacher->id], participants: [[$group->id, null]],
        ));

        $copy = $this->version->duplicate('Нова', '2027-02-01');

        $this->assertTrue($copy->isDraft());
        $this->assertSame([$teacher->id], $copy->lessons()->sole()->teachers->modelKeys());
        $this->assertSame(1, $this->version->lessons()->count());
    }
}
