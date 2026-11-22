<?php

namespace App\Filament\Resources\Buildings;

use App\Filament\Resources\Buildings\Pages\ManageBuildings;
use App\Models\Building;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class BuildingResource extends Resource
{
    protected static ?string $model = Building::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = 'Довідники';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'корпус';

    protected static ?string $pluralModelLabel = 'Корпуси';

    protected static bool $hasTitleCaseModelLabel = false;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('number')->label('Номер')->required()->maxLength(16)->unique(ignoreRecord: true),
            TextInput::make('name')->label('Назва')->required()->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('number')
            ->columns([
                TextColumn::make('number')->label('Номер')->sortable(),
                TextColumn::make('name')->label('Назва')->searchable(),
                TextColumn::make('rooms_count')->label('Аудиторій')->counts('rooms'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageBuildings::route('/')];
    }
}
