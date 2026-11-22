<?php

namespace Tests\Feature\Filament;

use App\Enums\GroupActivityKind;
use App\Enums\WeekParity;
use App\Filament\Pages\EducationalProcessChart;
use App\Filament\Resources\AcademicYears\AcademicYearResource;
use App\Filament\Resources\CalendarDays\CalendarDayResource;
use App\Filament\Resources\GroupActivities\GroupActivityResource;
use App\Filament\Resources\GroupActivities\Pages\ManageGroupActivities;
use App\Filament\Resources\PracticeTypes\PracticeTypeResource;
use App\Filament\Resources\Semesters\Pages\ManageSemesters;
use App\Filament\Resources\Semesters\SemesterResource;
use App\Models\AcademicYear;
use App\Models\GroupActivity;
use App\Models\PracticeType;
use App\Models\StudentGroup;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarAdminTest extends TestCase
{
    use RefreshDatabase;

    private AcademicYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->year = AcademicYear::create(['title' => '2026–2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30']);
        $this->year->semesters()->create(['number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-31']);
    }

    public function test_calendar_pages_render(): void
    {
        foreach ([AcademicYearResource::class, SemesterResource::class, CalendarDayResource::class, PracticeTypeResource::class, GroupActivityResource::class] as $resource) {
            $this->get($resource::getUrl())->assertOk();
        }

        StudentGroup::factory()->create(['code' => '4КГ-10']);
        $this->get(EducationalProcessChart::getUrl())->assertOk()->assertSee('4КГ-10');
    }

    public function test_semester_override_is_saved_as_monday(): void
    {
        $semester = $this->year->semesters()->first();

        Livewire::test(ManageSemesters::class)
            ->callAction(TestAction::make(EditAction::getDefaultName())->table($semester), data: [
                'parityOverrides' => [['week_start' => '2026-10-08', 'parity' => WeekParity::Numerator->value, 'note' => null]],
            ])
            ->assertHasNoFormErrors();

        $this->assertSame('2026-10-05', $semester->parityOverrides()->sole()->week_start->toDateString());
    }

    public function test_period_is_created_for_several_groups_and_overlaps_are_rejected(): void
    {
        [$a, $b] = StudentGroup::factory()->count(2)->create();
        $type = PracticeType::create(['name' => 'Технологічна', 'short_name' => 'Т', 'color' => '#ff0000']);

        Livewire::test(ManageGroupActivities::class)
            ->callAction(CreateAction::class, data: [
                'student_group_ids' => [$a->id, $b->id], 'kind' => GroupActivityKind::Practice->value,
                'practice_type_id' => $type->id, 'starts_on' => '2026-10-05', 'ends_on' => '2026-10-25',
            ])
            ->assertHasNoFormErrors();

        $this->assertSame(2, GroupActivity::count());

        Livewire::test(ManageGroupActivities::class)
            ->callAction(CreateAction::class, data: [
                'student_group_ids' => [$a->id], 'kind' => GroupActivityKind::Session->value,
                'starts_on' => '2026-10-20', 'ends_on' => '2026-10-30',
            ])
            ->assertHasFormErrors(['ends_on']);

        $this->assertSame(2, GroupActivity::count());
    }

    public function test_chart_marks_practice_weeks_and_vacation(): void
    {
        $group = StudentGroup::factory()->create();
        $type = PracticeType::create(['name' => 'Технологічна', 'short_name' => 'ТП', 'color' => '#ff0000']);
        $group->activities()->create(['kind' => GroupActivityKind::Practice, 'practice_type_id' => $type->id, 'starts_on' => '2026-10-05', 'ends_on' => '2026-10-25']);

        $cells = Livewire::test(EducationalProcessChart::class)->instance()->cells[$group->id];

        $this->assertNull($cells['2026-09-28'], 'Теорія — порожня клітинка.');
        $this->assertSame('ТП', $cells['2026-10-05']['short']);
        $this->assertSame('#ff0000', $cells['2026-10-19']['color']);
        $this->assertSame('К', $cells['2027-01-04']['short'], 'Поза семестрами — канікули.');
    }
}
