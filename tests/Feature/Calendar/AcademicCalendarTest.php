<?php

namespace Tests\Feature\Calendar;

use App\Calendar\AcademicCalendar;
use App\Enums\CalendarDayKind;
use App\Enums\GroupActivityKind;
use App\Enums\ScheduleVersionStatus;
use App\Enums\Weekday;
use App\Enums\WeekParity;
use App\Models\AcademicYear;
use App\Models\CalendarDay;
use App\Models\Discipline;
use App\Models\ScheduleVersion;
use App\Models\Semester;
use App\Models\StudentGroup;
use App\Models\Teacher;
use App\Schedule\DailySchedule;
use App\Schedule\LessonDraft;
use App\Schedule\ScheduleEditor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();

        $year = AcademicYear::create(['title' => '2026–2027', 'starts_on' => '2026-09-01', 'ends_on' => '2027-06-30']);
        // 01.09.2026 — вівторок; тиждень з 31.08 — чисельник.
        $this->semester = $year->semesters()->create([
            'number' => 1, 'starts_on' => '2026-09-01', 'ends_on' => '2026-12-31', 'first_week_parity' => WeekParity::Numerator,
        ]);
    }

    private function calendar(): AcademicCalendar
    {
        return new AcademicCalendar;
    }

    private function d(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date);
    }

    public function test_weeks_alternate_by_calendar_weeks(): void
    {
        $c = $this->calendar();

        $this->assertSame(WeekParity::Numerator, $c->day($this->d('2026-09-01'))->parity);
        $this->assertSame(WeekParity::Numerator, $c->day($this->d('2026-09-05'))->parity);
        $this->assertSame(WeekParity::Denominator, $c->day($this->d('2026-09-07'))->parity);
        $this->assertSame(WeekParity::Numerator, $c->day($this->d('2026-09-14'))->parity);
        // 12 тижнів після 31.08 — 23.11, парний відступ.
        $this->assertSame(WeekParity::Numerator, $c->day($this->d('2026-11-23'))->parity);
    }

    public function test_override_restarts_alternation_from_that_week(): void
    {
        // Тиждень 05.10 мав би бути знаменником (5 тижнів від 31.08), робимо чисельником.
        $this->semester->parityOverrides()->create(['week_start' => '2026-10-07', 'parity' => WeekParity::Numerator]);
        $semester = $this->semester->fresh();

        $this->assertSame('2026-10-05', $semester->parityOverrides->first()->week_start->toDateString(), 'Зберігається понеділок тижня.');
        $this->assertSame(WeekParity::Numerator, $semester->parityOn($this->d('2026-09-28')));
        $this->assertSame(WeekParity::Numerator, $semester->parityOn($this->d('2026-10-05')));
        $this->assertSame(WeekParity::Denominator, $semester->parityOn($this->d('2026-10-12')));
        $this->assertSame(WeekParity::Numerator, $semester->naturalParityOn($this->d('2026-10-12')), 'Без перевизначення — зсув видно.');
    }

    public function test_sunday_holiday_and_out_of_semester_have_no_classes(): void
    {
        CalendarDay::create(['date' => '2026-10-14', 'kind' => CalendarDayKind::Holiday, 'note' => 'День захисників']);
        $c = $this->calendar();

        $this->assertFalse($c->day($this->d('2026-09-06'))->isStudyDay);
        $this->assertFalse($c->day($this->d('2026-10-14'))->isStudyDay);
        $this->assertSame(WeekParity::Numerator, $c->day($this->d('2026-10-14'))->parity, 'Тип тижня видно і у свято.');
        $this->assertFalse($c->day($this->d('2027-01-05'))->isStudyDay);
        $this->assertTrue($c->day($this->d('2026-09-05'))->isStudyDay, 'Субота — навчальний день; чи є пари, вирішують слоти зміни.');
    }

    public function test_day_follows_schedule_of_another_day(): void
    {
        CalendarDay::create([
            'date' => '2026-11-21', 'kind' => CalendarDayKind::Working,
            'follows_weekday' => Weekday::Tuesday, 'follows_parity' => WeekParity::Denominator,
        ]);

        $day = $this->calendar()->day($this->d('2026-11-21'));

        $this->assertTrue($day->isStudyDay);
        $this->assertTrue($day->isRescheduled);
        $this->assertSame(Weekday::Tuesday, $day->scheduleWeekday);
        $this->assertSame(WeekParity::Denominator, $day->parity);
        $this->assertSame('Субота 21.11.2026 — за розкладом: вівторок, знаменник', $day->describe());
    }

    public function test_group_state_defaults_and_periods(): void
    {
        $group = StudentGroup::factory()->create();
        $group->activities()->create(['kind' => GroupActivityKind::Practice, 'starts_on' => '2026-10-05', 'ends_on' => '2026-10-25']);
        $c = $this->calendar();

        $this->assertSame(GroupActivityKind::Theory, $c->groupStateOn($group, $this->d('2026-10-02')));
        $this->assertSame(GroupActivityKind::Practice, $c->groupStateOn($group, $this->d('2026-10-25')));
        $this->assertSame(GroupActivityKind::Vacation, $c->groupStateOn($group, $this->d('2027-01-10')));
    }

    public function test_daily_schedule_uses_published_version_parity_and_group_state(): void
    {
        $group = StudentGroup::factory()->create();
        $teacher = Teacher::factory()->create();
        $version = ScheduleVersion::factory()->create(['effective_from' => '2026-09-01']);
        $editor = app(ScheduleEditor::class);
        $lesson = fn (Weekday $day, int $pair, WeekParity $week) => $editor->save(new LessonDraft(
            versionId: $version->id, weekday: $day, pair: $pair, week: $week,
            disciplineId: Discipline::factory()->create()->id, teacherIds: [$teacher->id], participants: [[$group->id, null]],
        ));
        $lesson(Weekday::Tuesday, 1, WeekParity::Every);
        $lesson(Weekday::Tuesday, 2, WeekParity::Numerator);
        $lesson(Weekday::Tuesday, 3, WeekParity::Denominator);

        $daily = app(DailySchedule::class);
        $pairs = fn (string $date) => $daily->forGroup($group, $this->d($date))->pluck('pair')->all();

        $this->assertSame([], $pairs('2026-09-01'), 'Чернетка ще не діє.');

        $version->update(['status' => ScheduleVersionStatus::Published]);

        $this->assertSame([1, 2], $pairs('2026-09-01'));
        $this->assertSame([1, 3], $pairs('2026-09-08'));

        CalendarDay::create(['date' => '2026-11-21', 'kind' => CalendarDayKind::Working, 'follows_weekday' => Weekday::Tuesday, 'follows_parity' => WeekParity::Denominator]);
        $this->assertSame([1, 3], $pairs('2026-11-21'));

        $group->activities()->create(['kind' => GroupActivityKind::Practice, 'starts_on' => '2026-10-05', 'ends_on' => '2026-10-25']);
        $this->assertSame([], $pairs('2026-10-06'));
        $this->assertSame([], $daily->forTeacher($teacher, $this->d('2026-10-06'))->all());
        $this->assertCount(2, $daily->forTeacher($teacher, $this->d('2026-10-27')));
    }
}
