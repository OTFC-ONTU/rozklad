<?php

namespace App\Filament\Resources\Disciplines;

use App\Enums\RoomType;
use App\Filament\Resources\Disciplines\Pages\ManageDisciplines;
use App\Models\Discipline;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class DisciplineResource extends Resource
{
    protected static ?string $model = Discipline::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Довідники';

    protected static ?int $navigationSort = 30;

    protected static ?string $modelLabel = 'дисципліна';

    protected static ?string $pluralModelLabel = 'Дисципліни';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Повна назва')->required()->maxLength(255),
            TextInput::make('short_name')->label('Скорочена назва')->required()->maxLength(255)
                ->helperText('Для друкованого розкладу.'),
            Select::make('room_type')->label('Потрібний тип аудиторії')->options(RoomType::class)
                ->placeholder('Будь-яка'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Назва')->searchable()->sortable(),
                TextColumn::make('short_name')->label('Скорочено')->searchable(),
                TextColumn::make('room_type')->label('Аудиторія')->badge()->placeholder('Будь-яка'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDisciplines::route('/')];
    }
}
