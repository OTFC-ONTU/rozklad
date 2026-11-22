<?php

namespace App\Filament\Resources\AcademicYears;

use App\Filament\Resources\AcademicYears\Pages\ManageAcademicYears;
use App\Models\AcademicYear;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AcademicYearResource extends Resource
{
    protected static ?string $model = AcademicYear::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static string|UnitEnum|null $navigationGroup = 'Календар';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'навчальний рік';

    protected static ?string $pluralModelLabel = 'Навчальні роки';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Назва')->required()->maxLength(32)->unique(ignoreRecord: true)
                ->placeholder('2026–2027'),
            DatePicker::make('starts_on')->label('Початок')->required(),
            DatePicker::make('ends_on')->label('Кінець')->required()->after('starts_on'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
            ->columns([
                TextColumn::make('title')->label('Рік'),
                TextColumn::make('starts_on')->label('Початок')->date('d.m.Y'),
                TextColumn::make('ends_on')->label('Кінець')->date('d.m.Y'),
                TextColumn::make('semesters_count')->label('Семестрів')->counts('semesters'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAcademicYears::route('/')];
    }
}
