<?php

namespace App\Filament\Pages;

use App\Enums\LessonFormat;
use App\Enums\Shift;
use App\Models\Discipline;
use App\Models\Lesson;
use App\Models\Room;
use App\Models\SlotSetting;
use App\Models\StudentGroup;
use App\Models\Subgroup;
use App\Models\Teacher;
use App\Schedule\ConflictChecker;
use App\Schedule\ConflictReport;
use App\Schedule\DailySchedule;
use App\Schedule\DayEditor;
use App\Schedule\DayLesson;
use App\Schedule\DayPlan;
use App\Schedule\LessonDraft;
use App\Schedule\ScheduleConflictException;
use App\Schedule\SubstitutionHints;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use UnitEnum;

/**
 * Редагування дня як на паперовому листку обліку: рядки — викладачі зміни, колонки — пари.
 * Скасування одним кліком, заміна/перенесення/додаткова пара — у формі з перевіркою конфліктів.
 */
class DailyChanges extends Page
{
    protected string $view = 'filament.pages.daily-changes';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Розклад';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Заміни на день';

    protected static ?string $title = 'Заміни на день';

    #[Url]
    public ?string $date = null;

    #[Url]
    public int $shift = 1;

    /** @var array<string, ConflictReport|null> кеш перевірок у межах запиту */
    private array $reports = [];

    private const array DRAFT_FIELDS = ['key', 'pair', 'groups', 'subgroup_id', 'discipline_id', 'teachers', 'rooms', 'format', 'online_url'];

    public function mount(): void
    {
        $this->date = $this->parsedDate()->toDateString();
        $this->shift = Shift::tryFrom($this->shift)?->value ?? Shift::First->value;
    }

    private function parsedDate(): CarbonImmutable
    {
        try {
            return $this->date ? CarbonImmutable::parse($this->date)->startOfDay() : CarbonImmutable::today();
        } catch (\Throwable) {
            return CarbonImmutable::today();
        }
    }

    public function currentShift(): Shift
    {
        return Shift::from($this->shift);
    }

    #[Computed]
    public function plan(): DayPlan
    {
        return app(DailySchedule::class)->plan($this->parsedDate());
    }

    /** @return Collection<int, DayLesson> пари обраної зміни разом зі скасованими */
    #[Computed]
    public function shiftLessons(): Collection
    {
        return $this->plan->inShift($this->currentShift());
    }

    /** @return Collection<int, Teacher> */
    #[Computed]
    public function teachers(): Collection
    {
        $ids = $this->shiftLessons->flatMap->teacherIds()->unique();

        return Teacher::whereKey($ids)->orderBy('short_name')->get();
    }

    /** @return array<int, array<int, list<DayLesson>>> [id викладача][пара] => пари */
    #[Computed]
    public function cells(): array
    {
        $cells = [];

        foreach ($this->shiftLessons as $lesson) {
            foreach ($lesson->teacherIds() as $teacherId) {
                $cells[$teacherId][$lesson->pair()][] = $lesson;
            }
        }

        return $cells;
    }

    /** @return Collection<int, array{group: StudentGroup, pair: int, cancelled: DayLesson, candidates: Collection}> */
    #[Computed]
    public function idleGroups(): Collection
    {
        $hints = app(SubstitutionHints::class);

        return $hints->idleGroups($this->plan, $this->currentShift())
            ->map(fn (array $i) => [...$i, 'candidates' => $hints->candidates($this->plan, $i['group']->id, $i['pair'], $i['cancelled'])]);
    }

    /** @return list<int> */
    public function pairs(): array
    {
        return range(1, SlotSetting::MAX_PAIRS);
    }

    public function changeDate(int $days): void
    {
        $this->date = $this->parsedDate()->addDays($days)->toDateString();
        $this->refreshDay();
    }

    public function updatedDate(): void
    {
        $this->date = $this->parsedDate()->toDateString();
        $this->refreshDay();
    }

    public function updatedShift(): void
    {
        $this->shift = Shift::tryFrom((int) $this->shift)?->value ?? Shift::First->value;
        $this->refreshDay();
    }

    private function refreshDay(): void
    {
        unset($this->plan, $this->shiftLessons, $this->teachers, $this->cells, $this->idleGroups);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('attendanceSheet')
                ->label('Листок обліку')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn () => route('filament.admin.print.attendance', ['date' => $this->date, 'shift' => $this->shift]))
                ->openUrlInNewTab(),
            Action::make('changesSheet')
                ->label('Листок змін')
                ->icon(Heroicon::OutlinedPrinter)
                ->color('gray')
                ->url(fn () => route('filament.admin.print.changes', ['date' => $this->date, 'shift' => $this->shift]))
                ->openUrlInNewTab(),
            $this->addLessonAction()->label('Додати пару')->icon(Heroicon::OutlinedPlus),
        ];
    }

    public function addLessonAction(): Action
    {
        return Action::make('addLesson')
            ->modalHeading(fn () => 'Додаткова пара — '.$this->plan->day->describe())
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Зберегти')
            ->disabled(fn () => ! $this->plan->day->isStudyDay)
            ->schema(fn () => $this->lessonForm())
            ->fillForm(fn (array $arguments) => [
                'pair' => $arguments['pair'] ?? null,
                'teachers' => isset($arguments['teacher']) ? [$arguments['teacher']] : [],
                'groups' => isset($arguments['group']) ? [$arguments['group']] : [],
                'discipline_id' => $arguments['discipline'] ?? null,
                'format' => LessonFormat::InPerson->value,
            ])
            ->action(fn (array $data, Action $action) => $this->saveLesson($data, $action));
    }

    public function editLessonAction(): Action
    {
        return Action::make('editLesson')
            ->modalHeading(fn (array $arguments) => $this->findLesson($arguments['key'] ?? '')?->status->label() ?? 'Пара')
            ->modalDescription(fn () => $this->plan->day->describe())
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Зберегти заміну')
            ->schema(fn () => $this->lessonForm())
            ->fillForm(function (array $arguments) {
                $day = $this->findLesson($arguments['key']);
                $lesson = $day->lesson;

                return [
                    'key' => $day->key(),
                    'pair' => $lesson->pair,
                    'groups' => $day->groupIds(),
                    'subgroup_id' => $lesson->participants->first()?->subgroup_id,
                    'discipline_id' => $lesson->discipline_id,
                    'teachers' => $day->teacherIds(),
                    'rooms' => $lesson->rooms->modelKeys(),
                    'format' => $lesson->format->value,
                    'online_url' => $lesson->online_url,
                    'reason' => $day->reason(),
                    'override_reason' => $day->change?->override_reason,
                ];
            })
            ->extraModalFooterActions(fn (array $arguments) => array_filter([
                $this->findLesson($arguments['key'] ?? '')?->isCancelled() ? null : Action::make('cancelFromEdit')
                    ->label('Скасувати пару')
                    ->color('danger')
                    ->cancelParentActions()
                    ->action(fn () => $this->cancel($arguments['key'], null)),
                $this->findLesson($arguments['key'] ?? '')?->original && $this->findLesson($arguments['key'])->isChanged()
                    ? Action::make('restoreFromEdit')
                        ->label('Як за розкладом')
                        ->color('gray')
                        ->cancelParentActions()
                        ->action(fn () => $this->restore($arguments['key']))
                    : null,
            ]))
            ->action(fn (array $data, Action $action) => $this->saveLesson($data, $action));
    }

    public function cancelLessonAction(): Action
    {
        return Action::make('cancelLesson')
            ->modalHeading('Скасувати пару?')
            ->modalDescription(fn (array $arguments) => $this->describeLesson($this->findLesson($arguments['key'] ?? '')))
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel('Скасувати пару')
            ->color('danger')
            ->schema([
                TextInput::make('reason')->label('Причина')->placeholder('Лікарняний, відрядження…')->maxLength(255),
            ])
            ->action(fn (array $data, array $arguments) => $this->cancel($arguments['key'], $data['reason'] ?? null));
    }

    public function restoreLessonAction(): Action
    {
        return Action::make('restoreLesson')
            ->requiresConfirmation()
            ->modalHeading('Повернути пару як за розкладом?')
            ->modalDescription(fn (array $arguments) => $this->describeLesson($this->findLesson($arguments['key'] ?? '')))
            ->action(fn (array $arguments) => $this->restore($arguments['key']));
    }

    private function cancel(string $key, ?string $reason): void
    {
        $lesson = $this->findLesson($key);

        if ($lesson === null) {
            return;
        }

        app(DayEditor::class)->cancel($this->plan, $lesson, $reason);
        $this->refreshDay();
        Notification::make()->success()->title($lesson->original ? 'Пару скасовано.' : 'Додаткову пару видалено.')->send();
    }

    private function restore(string $key): void
    {
        $lesson = $this->findLesson($key);

        if ($lesson === null) {
            return;
        }

        app(DayEditor::class)->restore($lesson);
        $this->refreshDay();
        Notification::make()->success()->title('Пару повернуто як за розкладом.')->send();
    }

    private function saveLesson(array $data, Action $action): void
    {
        $draft = $this->draftFromState($data);

        if ($draft === null) {
            Notification::make()->danger()->title('Заповніть пару, групи й дисципліну.')->send();
            $action->halt();
        }

        try {
            app(DayEditor::class)->save(
                $this->plan, $draft, $this->findLesson($data['key'] ?? ''), $data['reason'] ?? null, $data['override_reason'] ?? null,
            );
        } catch (ScheduleConflictException $e) {
            Notification::make()->danger()->title('Заміну не збережено')
                ->body($e->report->hardConflicts()->pluck('message')->implode("\n"))->send();
            $action->halt();
        }

        $this->refreshDay();
        Notification::make()->success()->title('Заміну збережено.')->send();
    }

    public function findLesson(?string $key): ?DayLesson
    {
        return $key ? $this->plan->find($key) : null;
    }

    public function describeLesson(?DayLesson $lesson): string
    {
        if ($lesson === null) {
            return '';
        }

        $l = $lesson->lesson;

        return sprintf(
            '%d пара: %s, %s%s',
            $l->pair,
            $l->participants->map->label()->implode(', '),
            $l->displayTitle(),
            $l->teachers->isNotEmpty() ? ' — '.$l->teachers->pluck('short_name')->implode(', ') : '',
        );
    }

    /** @return array<int, mixed> */
    private function lessonForm(): array
    {
        $pairs = array_combine($this->pairs(), array_map(fn ($p) => "{$p} пара", $this->pairs()));

        return [
            Hidden::make('key'),
            Grid::make(3)->schema([
                Select::make('pair')->label('Пара')->options($pairs)->required()->live(),
                Select::make('groups')->label('Групи')
                    ->helperText('Кілька груп — потік.')
                    ->multiple()->required()->searchable()->live()
                    ->columnSpan(2)
                    ->options(fn (Get $get) => $this->groupOptions($get)),
            ]),
            Grid::make(2)->schema([
                Select::make('subgroup_id')->label('Підгрупа')
                    ->placeholder('Уся група')
                    ->live()
                    ->visible(fn (Get $get) => count($get('groups') ?? []) === 1)
                    ->options(fn (Get $get) => Subgroup::where('student_group_id', ($get('groups') ?? [null])[0])
                        ->orderBy('division')->orderBy('name')->get()
                        ->mapWithKeys(fn (Subgroup $s) => [$s->id => "{$s->division}: {$s->name}"])),
                Select::make('discipline_id')->label('Дисципліна')
                    ->searchable()->live()->required()
                    ->options(fn () => Discipline::orderBy('name')->pluck('name', 'id')),
            ]),
            Grid::make(2)->schema([
                Select::make('teachers')->label('Викладачі')
                    ->helperText('Спершу вільні, що ведуть цю дисципліну.')
                    ->multiple()->searchable()->live()
                    ->options(fn (Get $get) => $this->teacherOptions($get)),
                Select::make('rooms')->label('Аудиторії')
                    ->multiple()->searchable()->live()
                    ->visible(fn (Get $get) => ($get('format') instanceof LessonFormat ? $get('format') : LessonFormat::tryFrom((string) $get('format'))) !== LessonFormat::Remote)
                    ->options(fn (Get $get) => $this->roomOptions($get)),
            ]),
            Grid::make(2)->schema([
                Select::make('format')->label('Формат')->options(LessonFormat::class)->required()->live(),
                TextInput::make('online_url')->label('Посилання')->url()->maxLength(255)
                    ->visible(fn (Get $get) => ($get('format') instanceof LessonFormat ? $get('format') : LessonFormat::tryFrom((string) $get('format'))) === LessonFormat::Remote),
            ]),
            TextInput::make('reason')->label('Причина заміни')->placeholder('Лікарняний, відрядження…')->maxLength(255),
            View::make('filament.pages.partials.lesson-conflicts')
                ->viewData(fn (Get $get) => ['report' => $this->reportFor($get)]),
            Textarea::make('override_reason')->label('Причина збереження попри конфлікти')
                ->rows(2)
                ->visible(fn (Get $get) => $this->reportFor($get)?->hasHard() ?? false)
                ->required(fn (Get $get) => $this->reportFor($get)?->hasHard() ?? false),
        ];
    }

    private function reportFor(Get $get): ?ConflictReport
    {
        $state = [];
        foreach (self::DRAFT_FIELDS as $field) {
            $state[$field] = $get($field);
        }

        $key = md5(serialize([$this->date, $state]));

        if (! array_key_exists($key, $this->reports)) {
            $draft = $this->draftFromState($state);
            $this->reports[$key] = $draft
                ? app(ConflictChecker::class)->checkOnDate($draft, $this->plan, $this->findLesson($state['key'] ?? null))
                : null;
        }

        return $this->reports[$key];
    }

    /** Чернетка з даних форми; null, якщо пара ще не описана. */
    private function draftFromState(array $s): ?LessonDraft
    {
        $groups = array_map('intval', $s['groups'] ?? []);
        $day = $this->plan->day;

        if (! $day->isStudyDay || empty($s['pair']) || $groups === [] || blank($s['discipline_id'] ?? null)) {
            return null;
        }

        $format = ($s['format'] ?? null) instanceof LessonFormat ? $s['format'] : (LessonFormat::tryFrom((string) ($s['format'] ?? '')) ?? LessonFormat::InPerson);
        $subgroup = count($groups) === 1 && filled($s['subgroup_id'] ?? null) ? (int) $s['subgroup_id'] : null;

        return new LessonDraft(
            versionId: $this->plan->version?->id ?? 0,
            weekday: $day->scheduleWeekday,
            pair: (int) $s['pair'],
            disciplineId: (int) $s['discipline_id'],
            teacherIds: array_map('intval', $s['teachers'] ?? []),
            roomIds: $format === LessonFormat::Remote ? [] : array_map('intval', $s['rooms'] ?? []),
            participants: array_map(fn (int $g) => [$g, $subgroup], $groups),
            format: $format,
            onlineUrl: $format === LessonFormat::Remote && filled($s['online_url'] ?? null) ? $s['online_url'] : null,
        );
    }

    /** @return Collection<int, DayLesson> інші пари цього дня в обраний слот */
    private function busyLessons(Get $get): Collection
    {
        $pair = (int) $get('pair');

        if ($pair === 0) {
            return collect();
        }

        return $this->plan->active()
            ->filter(fn (DayLesson $l) => $l->pair() === $pair && $l->key() !== $get('key'))
            ->values();
    }

    /** Групи зміни, що сьогодні навчаються; ті, що без пари в цей час, — спершу. */
    private function groupOptions(Get $get): array
    {
        $busy = $this->busyLessons($get)->flatMap->groupIds()->flip();
        $selected = array_map('intval', $get('groups') ?? []);

        return $this->plan->groups
            ->filter(fn (StudentGroup $g) => $g->shiftOn($this->plan->day->date) === $this->currentShift() || in_array($g->id, $selected, true))
            ->sortBy(fn (StudentGroup $g) => [$busy->has($g->id), $g->code])
            ->mapWithKeys(fn (StudentGroup $g) => [$g->id => $g->code.($busy->has($g->id) ? ' — має пару' : '')])
            ->all();
    }

    private function teacherOptions(Get $get): array
    {
        $busy = $this->busyLessons($get)->flatMap->teacherIds()->flip();
        $disciplineId = $get('discipline_id');
        $teaches = $disciplineId && $this->plan->version
            ? Lesson::where('schedule_version_id', $this->plan->version->id)->where('discipline_id', $disciplineId)
                ->with('teachers:id')->get()->flatMap->teachers->pluck('id')->flip()
            : collect();

        return Teacher::orderBy('short_name')->get()
            ->sortBy(fn (Teacher $t) => [$busy->has($t->id), ! $teaches->has($t->id), $t->short_name])
            ->mapWithKeys(fn (Teacher $t) => [$t->id => $t->short_name
                .($teaches->has($t->id) ? ' · веде дисципліну' : '')
                .($busy->has($t->id) ? ' — зайнятий' : '')])
            ->all();
    }

    private function roomOptions(Get $get): array
    {
        $busy = $this->busyLessons($get)->flatMap(fn (DayLesson $l) => $l->lesson->rooms)->countBy('id');

        return Room::with('building')->orderBy('number')->get()
            ->sortBy(fn (Room $r) => ($busy[$r->id] ?? 0) >= $r->max_groups)
            ->mapWithKeys(function (Room $r) use ($busy) {
                $taken = $busy[$r->id] ?? 0;
                $suffix = match (true) {
                    $taken >= $r->max_groups => ' — зайнята',
                    $taken > 0 => " — {$taken}/{$r->max_groups} груп",
                    default => '',
                };

                return [$r->id => $r->label().($r->capacity ? " ({$r->capacity} місць)" : '').$suffix];
            })
            ->all();
    }
}
