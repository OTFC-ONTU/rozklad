<?php

namespace App\Filament\Pages;

use App\Enums\GroupActivityKind;
use App\Models\AcademicYear;
use App\Models\GroupActivity;
use App\Models\Semester;
use App\Models\StudentGroup;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/** Електронна версія графіка освітнього процесу: групи × тижні навчального року. */
class EducationalProcessChart extends Page
{
    protected string $view = 'filament.pages.educational-process-chart';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Календар';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Графік освітнього процесу';

    protected static ?string $title = 'Графік освітнього процесу';

    #[Url]
    public ?int $year = null;

    #[Url]
    public ?int $course = null;

    public function mount(): void
    {
        if ($this->year === null || ! AcademicYear::whereKey($this->year)->exists()) {
            $this->year = AcademicYear::query()
                ->whereDate('starts_on', '<=', now())->orderByDesc('starts_on')->value('id')
                ?? AcademicYear::orderByDesc('starts_on')->value('id');
        }
    }

    #[Computed]
    public function academicYear(): ?AcademicYear
    {
        return $this->year ? AcademicYear::with('semesters.parityOverrides')->find($this->year) : null;
    }

    /** @return Collection<int, string> */
    #[Computed]
    public function yearOptions(): Collection
    {
        return AcademicYear::orderByDesc('starts_on')->pluck('title', 'id');
    }

    /** @return list<array{start: CarbonImmutable, parity: ?string, isStudy: bool}> */
    #[Computed]
    public function weeks(): array
    {
        $year = $this->academicYear;

        if (! $year) {
            return [];
        }

        $weeks = [];
        for ($w = CarbonImmutable::parse($year->starts_on)->startOfWeek(); $w->lte($year->ends_on); $w = $w->addWeek()) {
            $semester = $this->semesterOfWeek($w);
            $weeks[] = [
                'start' => $w,
                'parity' => $semester ? mb_substr($semester->parityOn($w)->label(), 0, 1) : null,
                'isStudy' => $semester !== null,
            ];
        }

        return $weeks;
    }

    /** @return Collection<int, StudentGroup> */
    #[Computed]
    public function groups(): Collection
    {
        return StudentGroup::query()
            ->when($this->course, fn ($q, $course) => $q->where('course', $course))
            ->orderBy('course')->orderBy('code')
            ->get();
    }

    /**
     * Клітинки графіка: для кожної групи й тижня — діяльність, що займає більшість робочих днів тижня.
     *
     * @return array<int, array<string, array{label: string, short: string, color: string, title: string}|null>>
     */
    #[Computed]
    public function cells(): array
    {
        $year = $this->academicYear;

        if (! $year) {
            return [];
        }

        $activities = GroupActivity::with('practiceType')
            ->whereIn('student_group_id', $this->groups->modelKeys())
            ->whereDate('ends_on', '>=', $year->starts_on)
            ->whereDate('starts_on', '<=', $year->ends_on)
            ->get()
            ->groupBy('student_group_id');

        $cells = [];
        foreach ($this->groups as $group) {
            foreach ($this->weeks as $week) {
                $cells[$group->id][$week['start']->toDateString()] = $this->cell(
                    $activities->get($group->id, collect()), $week['start'], $week['isStudy'],
                );
            }
        }

        return $cells;
    }

    public function updatedYear(): void
    {
        unset($this->academicYear, $this->weeks, $this->cells);
    }

    public function updatedCourse(): void
    {
        unset($this->groups, $this->cells);
    }

    /** @return array{label: string, short: string, color: string, title: string}|null null — теоретичне навчання */
    private function cell(Collection $activities, CarbonImmutable $monday, bool $isStudy): ?array
    {
        $days = collect(range(0, 4))->map(fn (int $i) => $monday->addDays($i));
        $covering = $activities
            ->map(fn (GroupActivity $a) => ['activity' => $a, 'days' => $days->filter(fn ($d) => $a->covers($d))->count()])
            ->filter(fn (array $x) => $x['days'] > 0)
            ->sortByDesc('days');

        if ($covering->isEmpty()) {
            return $isStudy ? null : [
                'label' => GroupActivityKind::Vacation->label(),
                'short' => GroupActivityKind::Vacation->abbreviation(),
                'color' => GroupActivityKind::Vacation->hex(),
                'title' => GroupActivityKind::Vacation->label(),
            ];
        }

        /** @var GroupActivity $main */
        $main = $covering->first()['activity'];

        return [
            'label' => $main->label(),
            'short' => $main->practiceType?->short_name ?? $main->kind->abbreviation(),
            'color' => $main->color(),
            'title' => $covering->map(fn (array $x) => sprintf(
                '%s: %s–%s', $x['activity']->label(), $x['activity']->starts_on->format('d.m'), $x['activity']->ends_on->format('d.m'),
            ))->implode("\n"),
        ];
    }

    private function semesterOfWeek(CarbonImmutable $monday): ?Semester
    {
        return $this->academicYear->semesters->first(
            fn (Semester $s) => collect(range(0, 4))->contains(fn (int $i) => $s->contains($monday->addDays($i)))
        );
    }

    /** @return Collection<int, array{label: string, short: string, color: string}> легенда за фактично використаними позначками */
    public function legend(): Collection
    {
        return collect($this->cells)->flatten(1)->filter()->unique('label')
            ->map(fn (array $c) => ['label' => $c['label'], 'short' => $c['short'], 'color' => $c['color']])
            ->values();
    }
}
