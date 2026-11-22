<?php

namespace App\Filament\Pages;

use App\Calendar\AcademicCalendar;
use App\Enums\LessonFormat;
use App\Enums\Weekday;
use App\Enums\WeekParity;
use App\Models\Discipline;
use App\Models\Lesson;
use App\Models\Room;
use App\Models\ScheduleVersion;
use App\Models\ShiftSetting;
use App\Models\StudentGroup;
use App\Models\Subgroup;
use App\Models\Teacher;
use App\Schedule\ConflictChecker;
use App\Schedule\ConflictReport;
use App\Schedule\LessonDraft;
use App\Schedule\ScheduleConflictException;
use App\Schedule\ScheduleEditor;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
 * Тижневий шаблон розкладу у вигляді паперової сітки: рядки — день × пара, колонки — групи.
 * Клік по клітинці додає пару, по парі — редагує; конфлікти показуються в формі одразу.
 */
class ScheduleGrid extends Page
{
    protected string $view = 'filament.pages.schedule-grid';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static string|UnitEnum|null $navigationGroup = 'Розклад';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Сітка розкладу';

    protected static ?string $title = 'Сітка розкладу';

    #[Url]
    public ?int $version = null;

    #[Url]
    public ?int $course = null;

    /** @var array<string, ConflictReport|null> кеш перевірок у межах запиту (не серіалізується Livewire) */
    private array $reports = [];

    /** Поля форми пари, з яких складається LessonDraft. */
    private const array DRAFT_FIELDS = [
        'lesson_id', 'weekday', 'pair', 'week', 'discipline_id', 'title', 'is_fixed_block',
        'groups', 'subgroup_id', 'teachers', 'rooms', 'format', 'online_url', 'is_pinned',
    ];

    public function mount(): void
    {
        if ($this->version === null || ! ScheduleVersion::whereKey($this->version)->exists()) {
            $this->version = ScheduleVersion::query()
                ->orderByRaw("case when status = 'draft' then 0 else 1 end")
                ->orderByDesc('effective_from')
                ->value('id');
        }
    }

    #[Computed]
    public function currentVersion(): ?ScheduleVersion
    {
        return $this->version ? ScheduleVersion::find($this->version) : null;
    }

    /** «Понеділок 23.11.2026, чисельник» — для індикатора над сіткою. */
    #[Computed]
    public function today(): string
    {
        return app(AcademicCalendar::class)->day(now())->describe();
    }

    /** @return Collection<int, string> */
    #[Computed]
    public function versionOptions(): Collection
    {
        return ScheduleVersion::orderByDesc('effective_from')->get()
            ->mapWithKeys(fn (ScheduleVersion $v) => [
                $v->id => "{$v->title} (з {$v->effective_from->format('d.m.Y')}, {$v->status->label()})",
            ]);
    }

    /** @return Collection<int, StudentGroup> */
    #[Computed]
    public function groups(): Collection
    {
        return StudentGroup::query()
            ->when($this->course, fn ($q, $course) => $q->where('course', $course))
            ->orderBy('course')
            ->orderBy('code')
            ->get();
    }

    /** @return list<Weekday> Субота — лише якщо її десь увімкнено або в ній уже є пари. */
    #[Computed]
    public function weekdays(): array
    {
        $saturday = ShiftSetting::where('first_saturday_enabled', true)->orWhere('second_saturday_enabled', true)->exists()
            || ($this->version && Lesson::where('schedule_version_id', $this->version)->where('weekday', Weekday::Saturday)->exists());

        return array_values(array_filter(Weekday::cases(), fn (Weekday $d) => $saturday || $d !== Weekday::Saturday));
    }

    /**
     * @return array<int, array<int, array<int, list<Lesson>>>> [id групи][день][пара] => пари
     */
    #[Computed]
    public function cells(): array
    {
        if (! $this->version) {
            return [];
        }

        $cells = [];
        $lessons = Lesson::query()
            ->with(['discipline', 'teachers', 'rooms.building', 'participants.group', 'participants.subgroup'])
            ->where('schedule_version_id', $this->version)
            ->orderBy('week')
            ->get();

        foreach ($lessons as $lesson) {
            foreach ($lesson->participants->pluck('student_group_id')->unique() as $groupId) {
                $cells[$groupId][$lesson->weekday->value][$lesson->pair][] = $lesson;
            }
        }

        return $cells;
    }

    public function updatedVersion(): void
    {
        unset($this->currentVersion, $this->cells, $this->weekdays);
    }

    public function updatedCourse(): void
    {
        unset($this->groups);
    }

    public function createLessonAction(): Action
    {
        return Action::make('createLesson')
            ->modalHeading('Нова пара')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Зберегти')
            ->schema(fn () => $this->lessonForm())
            ->fillForm(fn (array $arguments) => [
                'weekday' => $arguments['weekday'],
                'pair' => $arguments['pair'],
                'week' => $arguments['week'] ?? WeekParity::Every->value,
                'groups' => [$arguments['group']],
                'format' => LessonFormat::InPerson->value,
            ])
            ->action(fn (array $data, Action $action) => $this->saveLesson($data, $action));
    }

    public function editLessonAction(): Action
    {
        return Action::make('editLesson')
            ->modalHeading('Редагування пари')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalSubmitActionLabel('Зберегти')
            ->schema(fn () => $this->lessonForm())
            ->fillForm(function (array $arguments) {
                $lesson = Lesson::with(['participants', 'teachers', 'rooms'])->findOrFail($arguments['lesson']);

                return [
                    'lesson_id' => $lesson->id,
                    'weekday' => $lesson->weekday->value,
                    'pair' => $lesson->pair,
                    'week' => $lesson->week->value,
                    'discipline_id' => $lesson->discipline_id,
                    'title' => $lesson->title,
                    'is_fixed_block' => $lesson->is_fixed_block,
                    'groups' => $lesson->participants->pluck('student_group_id')->unique()->values()->all(),
                    'subgroup_id' => $lesson->participants->first()?->subgroup_id,
                    'teachers' => $lesson->teachers->modelKeys(),
                    'rooms' => $lesson->rooms->modelKeys(),
                    'format' => $lesson->format->value,
                    'online_url' => $lesson->online_url,
                    'is_pinned' => $lesson->is_pinned,
                    'override_reason' => $lesson->override_reason,
                ];
            })
            ->extraModalFooterActions([
                Action::make('deleteLesson')
                    ->label('Видалити')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Видалити пару?')
                    ->cancelParentActions()
                    ->action(function (array $mountedActions) {
                        Lesson::whereKey($mountedActions[0]->getArguments()['lesson'])->delete();
                        $this->refreshGrid();
                        Notification::make()->success()->title('Пару видалено.')->send();
                    }),
            ])
            ->action(fn (array $data, Action $action) => $this->saveLesson($data, $action));
    }

    private function saveLesson(array $data, Action $action): void
    {
        try {
            app(ScheduleEditor::class)->save($this->draftFromState($data), $data['override_reason'] ?? null);
        } catch (ScheduleConflictException $e) {
            Notification::make()->danger()->title('Пару не збережено')
                ->body($e->report->hardConflicts()->pluck('message')->implode("\n"))->send();
            $action->halt();
        }

        $this->refreshGrid();
        Notification::make()->success()->title('Пару збережено.')->send();
    }

    private function refreshGrid(): void
    {
        unset($this->cells, $this->weekdays);
    }

    /** @return array<int, mixed> */
    private function lessonForm(): array
    {
        $pairs = array_combine(range(1, ShiftSetting::MAX_PAIRS), array_map(fn ($p) => "{$p} пара", range(1, ShiftSetting::MAX_PAIRS)));

        return [
            Hidden::make('lesson_id'),
            Grid::make(3)->schema([
                Select::make('weekday')->label('День')->options(Weekday::class)->required()->live(),
                Select::make('pair')->label('Пара')->options($pairs)->required()->live(),
                Select::make('week')->label('Тиждень')->options(WeekParity::class)->required()->live(),
            ]),
            Grid::make(2)->schema([
                Select::make('groups')->label('Групи')
                    ->helperText('Кілька груп — потік.')
                    ->multiple()->required()->searchable()->live()
                    ->options(fn () => StudentGroup::orderBy('code')->pluck('code', 'id')),
                Select::make('subgroup_id')->label('Підгрупа')
                    ->placeholder('Уся група')
                    ->live()
                    ->visible(fn (Get $get) => count($get('groups') ?? []) === 1)
                    ->options(fn (Get $get) => Subgroup::where('student_group_id', ($get('groups') ?? [null])[0])
                        ->orderBy('division')->orderBy('name')->get()
                        ->mapWithKeys(fn (Subgroup $s) => [$s->id => "{$s->division}: {$s->name}"])),
            ]),
            Select::make('discipline_id')->label('Дисципліна')
                ->searchable()->live()
                ->required(fn (Get $get) => ! $get('is_fixed_block'))
                ->options(fn () => Discipline::orderBy('name')->pluck('name', 'id')),
            Grid::make(2)->schema([
                Toggle::make('is_fixed_block')->label('Фіксований блок')->live()
                    ->helperText('Класна година, методичний день тощо.'),
                TextInput::make('title')->label('Назва блоку')->maxLength(255)
                    ->visible(fn (Get $get) => (bool) $get('is_fixed_block'))
                    ->required(fn (Get $get) => (bool) $get('is_fixed_block') && ! $get('discipline_id')),
            ]),
            Grid::make(2)->schema([
                Select::make('teachers')->label('Викладачі')
                    ->multiple()->searchable()->live()
                    ->options(fn (Get $get) => $this->teacherOptions($get)),
                Select::make('rooms')->label('Аудиторії')
                    ->multiple()->searchable()->live()
                    ->visible(fn (Get $get) => $this->enumFrom(LessonFormat::class, $get('format')) !== LessonFormat::Remote)
                    ->options(fn (Get $get) => $this->roomOptions($get)),
            ]),
            Grid::make(3)->schema([
                Select::make('format')->label('Формат')->options(LessonFormat::class)->required()->live(),
                TextInput::make('online_url')->label('Посилання')->url()->maxLength(255)
                    ->visible(fn (Get $get) => $this->enumFrom(LessonFormat::class, $get('format')) === LessonFormat::Remote),
                Toggle::make('is_pinned')->label('Закріпити')->helperText('Генератор не змінюватиме пару.'),
            ]),
            View::make('filament.pages.partials.lesson-conflicts')
                ->viewData(fn (Get $get) => ['report' => $this->reportFor($get)]),
            Textarea::make('override_reason')->label('Причина збереження попри конфлікти')
                ->rows(2)
                ->visible(fn (Get $get) => $this->reportFor($get)?->hasHard() ?? false)
                ->required(fn (Get $get) => $this->reportFor($get)?->hasHard() ?? false),
        ];
    }

    /** Перевірка поточного стану форми; кешується в межах одного запиту. */
    private function reportFor(Get $get): ?ConflictReport
    {
        $state = [];
        foreach (self::DRAFT_FIELDS as $field) {
            $state[$field] = $get($field);
        }

        $key = md5(serialize([$this->version, $state]));

        if (! array_key_exists($key, $this->reports)) {
            $draft = $this->draftFromState($state);
            $this->reports[$key] = $draft ? app(ConflictChecker::class)->check($draft) : null;
        }

        return $this->reports[$key];
    }

    /** Чернетка з даних форми; null, якщо ще не вибрано слот або групу. */
    private function draftFromState(array $s): ?LessonDraft
    {
        $weekday = $this->enumFrom(Weekday::class, $s['weekday'] ?? null);
        $week = $this->enumFrom(WeekParity::class, $s['week'] ?? null);
        $groups = array_map('intval', $s['groups'] ?? []);

        $described = filled($s['discipline_id'] ?? null) || (($s['is_fixed_block'] ?? false) && filled($s['title'] ?? null));

        // Незаповнена форма — справа валідації, а не конфлікт: перевіряємо лише описану пару.
        if (! $this->version || ! $weekday || ! $week || empty($s['pair']) || $groups === [] || ! $described) {
            return null;
        }

        $subgroup = count($groups) === 1 && filled($s['subgroup_id'] ?? null) ? (int) $s['subgroup_id'] : null;
        $format = $this->enumFrom(LessonFormat::class, $s['format'] ?? null) ?? LessonFormat::InPerson;

        return new LessonDraft(
            versionId: $this->version,
            weekday: $weekday,
            pair: (int) $s['pair'],
            week: $week,
            disciplineId: filled($s['discipline_id'] ?? null) ? (int) $s['discipline_id'] : null,
            teacherIds: array_map('intval', $s['teachers'] ?? []),
            roomIds: $format === LessonFormat::Remote ? [] : array_map('intval', $s['rooms'] ?? []),
            participants: array_map(fn (int $g) => [$g, $subgroup], $groups),
            title: filled($s['title'] ?? null) ? $s['title'] : null,
            isFixedBlock: (bool) ($s['is_fixed_block'] ?? false),
            format: $format,
            onlineUrl: $format === LessonFormat::Remote ? ($s['online_url'] ?? null) : null,
            isPinned: (bool) ($s['is_pinned'] ?? false),
            lessonId: filled($s['lesson_id'] ?? null) ? (int) $s['lesson_id'] : null,
        );
    }

    /**
     * @template T of BackedEnum
     *
     * @param  class-string<T>  $enum
     * @return T|null
     */
    private function enumFrom(string $enum, mixed $value): ?BackedEnum
    {
        if ($value instanceof $enum || $value === null || $value === '') {
            return $value ?: null;
        }

        return $enum::tryFrom(is_numeric($value) ? (int) $value : $value) ?? $enum::tryFrom((string) $value);
    }

    /** Пари версії в слоті з форми, що можуть збігтися за тижнем (крім редагованої). */
    private function busyLessons(Get $get): Collection
    {
        $weekday = $this->enumFrom(Weekday::class, $get('weekday'));
        $week = $this->enumFrom(WeekParity::class, $get('week'));

        if (! $this->version || ! $weekday || ! $week || ! $get('pair')) {
            return collect();
        }

        return Lesson::query()
            ->with(['teachers', 'rooms'])
            ->where('schedule_version_id', $this->version)
            ->where('weekday', $weekday)
            ->where('pair', (int) $get('pair'))
            ->whereIn('week', $week->overlapping())
            ->when($get('lesson_id'), fn ($q, $id) => $q->whereKeyNot($id))
            ->get();
    }

    /** Підказка в клітинці: зайняті в цей час викладачі позначені і йдуть у кінці. */
    private function teacherOptions(Get $get): array
    {
        $busy = $this->busyLessons($get)->flatMap->teachers->pluck('id')->flip();

        return Teacher::orderBy('short_name')->get()
            ->sortBy(fn (Teacher $t) => $busy->has($t->id))
            ->mapWithKeys(fn (Teacher $t) => [$t->id => $t->short_name.($busy->has($t->id) ? ' — зайнятий' : '')])
            ->all();
    }

    private function roomOptions(Get $get): array
    {
        $busy = $this->busyLessons($get)->flatMap->rooms->countBy('id');

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
