<?php

namespace App\Filament\Resources\Teachers;

use App\Enums\ConstraintLevel;
use App\Enums\Weekday;
use App\Enums\WeekParity;
use App\Filament\Resources\Teachers\Pages\ManageTeachers;
use App\Models\SlotSetting;
use App\Models\Teacher;
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
use Filament\Tables\Table;
use UnitEnum;

class TeacherResource extends Resource
{
    protected static ?string $model = Teacher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static string|UnitEnum|null $navigationGroup = 'Довідники';

    protected static ?int $navigationSort = 40;

    protected static ?string $modelLabel = 'викладач';

    protected static ?string $pluralModelLabel = 'Викладачі';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'short_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('full_name')->label('ПІБ')->required()->maxLength(255),
            TextInput::make('short_name')->label('Коротко')->required()->maxLength(255)
                ->placeholder('Кривченко Ю.В.'),
            Repeater::make('constraints')
                ->label('Обмеження')
                ->relationship()
                ->columnSpanFull()
                ->columns(4)
                ->defaultItems(0)
                ->addActionLabel('Додати обмеження')
                ->schema([
                    Select::make('weekday')->label('День')->options(Weekday::class)->required(),
                    Select::make('pair')->label('Пара')->placeholder('Увесь день')
                        ->options(array_combine(range(1, SlotSetting::MAX_PAIRS), range(1, SlotSetting::MAX_PAIRS))),
                    Select::make('week')->label('Тиждень')->options(WeekParity::class)->default(WeekParity::Every)->required(),
                    Select::make('level')->label('Рівень')->options(ConstraintLevel::class)->default(ConstraintLevel::Forbidden)->required(),
                    DatePicker::make('starts_on')->label('З дати'),
                    DatePicker::make('ends_on')->label('По дату')->afterOrEqual('starts_on'),
                    TextInput::make('note')->label('Примітка')->columnSpan(2),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('full_name')
            ->columns([
                TextColumn::make('full_name')->label('ПІБ')->searchable()->sortable(),
                TextColumn::make('short_name')->label('Коротко')->searchable(),
                TextColumn::make('constraints_count')->label('Обмежень')->counts('constraints'),
            ])
            ->recordActions([EditAction::make()->modalWidth('5xl'), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTeachers::route('/')];
    }
}
