<?php

namespace App\Filament\Resources\Semesters;

use App\Enums\WeekParity;
use App\Filament\Resources\Semesters\Pages\ManageSemesters;
use App\Models\Semester;
use App\Models\WeekParityOverride;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;
use UnitEnum;

class SemesterResource extends Resource
{
    protected static ?string $model = Semester::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Календар';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'семестр';

    protected static ?string $pluralModelLabel = 'Семестри';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(3)->columnSpanFull()->schema([
                Select::make('academic_year_id')->label('Навчальний рік')->relationship('academicYear', 'title')->required()->preload(),
                Select::make('number')->label('Семестр')->options([1 => '1 семестр', 2 => '2 семестр'])->required(),
                Select::make('first_week_parity')->label('Перший тиждень')->required()->live()
                    ->options([WeekParity::Numerator->value => 'Чисельник', WeekParity::Denominator->value => 'Знаменник'])
                    ->default(WeekParity::Numerator->value),
                DatePicker::make('starts_on')->label('Початок')->required()->live(),
                DatePicker::make('ends_on')->label('Кінець')->required()->after('starts_on')->live(),
            ]),
            Repeater::make('parityOverrides')
                ->label('Ручне перевизначення тижнів')
                ->helperText('З указаного тижня чергування починається заново із заданого типу.')
                ->relationship()
                ->columnSpanFull()
                ->columns(3)
                ->defaultItems(0)
                ->live()
                ->addActionLabel('Перевизначити тиждень')
                ->schema([
                    DatePicker::make('week_start')->label('Тиждень (будь-який його день)')->required(),
                    Select::make('parity')->label('Тип')->required()
                        ->options([WeekParity::Numerator->value => 'Чисельник', WeekParity::Denominator->value => 'Знаменник']),
                    TextInput::make('note')->label('Причина')->maxLength(255),
                ]),
            View::make('filament.resources.semesters.weeks-preview')
                ->columnSpanFull()
                ->viewData(fn (Get $get) => ['weeks' => self::previewWeeks($get)]),
        ]);
    }

    /**
     * Тижні семестру з типом і позначкою, де чергування зсунулось через перевизначення.
     *
     * @return list<array{start: CarbonImmutable, parity: WeekParity, shifted: bool, override: bool}>
     */
    private static function previewWeeks(Get $get): array
    {
        try {
            $semester = new Semester([
                'starts_on' => $get('starts_on'),
                'ends_on' => $get('ends_on'),
                'first_week_parity' => $get('first_week_parity'),
            ]);

            if (! $semester->starts_on || ! $semester->ends_on || ! $semester->first_week_parity || $semester->ends_on->lt($semester->starts_on)) {
                return [];
            }

            $overrides = collect($get('parityOverrides') ?? [])
                ->filter(fn (array $o) => filled($o['week_start'] ?? null) && filled($o['parity'] ?? null))
                ->map(fn (array $o) => new WeekParityOverride(['week_start' => $o['week_start'], 'parity' => $o['parity']]))
                ->sortBy(fn (WeekParityOverride $o) => $o->week_start)
                ->values();
            $semester->setRelation('parityOverrides', $overrides);
        } catch (Throwable) {
            return [];
        }

        return array_map(fn (CarbonImmutable $week) => [
            'start' => $week,
            'parity' => $parity = $semester->parityOn($week),
            'shifted' => $parity !== $semester->naturalParityOn($week),
            'override' => $overrides->contains(fn (WeekParityOverride $o) => $o->week_start->equalTo($week)),
        ], array_slice($semester->weekStarts(), 0, 60));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['academicYear', 'parityOverrides']))
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('academicYear.title')->label('Рік'),
                TextColumn::make('number')->label('Семестр'),
                TextColumn::make('starts_on')->label('Початок')->date('d.m.Y'),
                TextColumn::make('ends_on')->label('Кінець')->date('d.m.Y'),
                TextColumn::make('first_week_parity')->label('Перший тиждень')->badge(),
                TextColumn::make('current')->label('Цей тиждень')->badge()->placeholder('—')
                    ->state(fn (Semester $s) => $s->contains(now()) ? $s->parityOn(now())->label() : null),
                TextColumn::make('parity_overrides_count')->label('Перевизначень')->counts('parityOverrides'),
            ])
            ->recordActions([EditAction::make()->modalWidth('4xl'), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSemesters::route('/')];
    }
}
