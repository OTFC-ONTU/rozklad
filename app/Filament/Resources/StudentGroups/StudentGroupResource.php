<?php

namespace App\Filament\Resources\StudentGroups;

use App\Enums\Shift;
use App\Filament\Resources\StudentGroups\Pages\ManageStudentGroups;
use App\Models\StudentGroup;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class StudentGroupResource extends Resource
{
    protected static ?string $model = StudentGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Довідники';

    protected static ?int $navigationSort = 50;

    protected static ?string $modelLabel = 'група';

    protected static ?string $pluralModelLabel = 'Групи';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(4)->components([
            TextInput::make('code')->label('Шифр')->required()->maxLength(16)->unique(ignoreRecord: true)
                ->placeholder('4КГ-10'),
            Select::make('course')->label('Курс')->options([1 => 1, 2 => 2, 3 => 3, 4 => 4])->required(),
            TextInput::make('students_count')->label('Здобувачів')->numeric()->minValue(1),
            Select::make('shift')->label('Зміна')->options(Shift::class)->default(Shift::First)->required(),

            Repeater::make('subgroups')
                ->label('Підгрупи')
                ->helperText('Підгрупи одного поділу займаються паралельно; різні поділи перетинаються за складом.')
                ->relationship()
                ->columnSpanFull()
                ->columns(3)
                ->defaultItems(0)
                ->addActionLabel('Додати підгрупу')
                ->schema([
                    TextInput::make('division')->label('Поділ')->required()->maxLength(64)->placeholder('Англійська'),
                    TextInput::make('name')->label('Назва')->required()->maxLength(64)->placeholder('1'),
                    TextInput::make('students_count')->label('Здобувачів')->numeric()->minValue(1),
                ]),

            Repeater::make('shiftChanges')
                ->label('Зміна на період')
                ->helperText('З указаної дати група навчається в іншу зміну.')
                ->relationship()
                ->columnSpanFull()
                ->columns(2)
                ->defaultItems(0)
                ->addActionLabel('Додати')
                ->schema([
                    DatePicker::make('effective_from')->label('З дати')->required(),
                    Select::make('shift')->label('Зміна')->options(Shift::class)->required(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->columns([
                TextColumn::make('code')->label('Шифр')->searchable()->sortable(),
                TextColumn::make('course')->label('Курс')->sortable(),
                TextColumn::make('students_count')->label('Здобувачів'),
                TextColumn::make('shift')->label('Зміна')->badge(),
                TextColumn::make('subgroups_count')->label('Підгруп')->counts('subgroups'),
            ])
            ->filters([
                SelectFilter::make('course')->label('Курс')->options([1 => 1, 2 => 2, 3 => 3, 4 => 4]),
            ])
            ->recordActions([EditAction::make()->modalWidth('4xl'), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStudentGroups::route('/')];
    }
}
