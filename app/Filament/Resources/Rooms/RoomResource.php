<?php

namespace App\Filament\Resources\Rooms;

use App\Enums\RoomType;
use App\Filament\Resources\Rooms\Pages\ManageRooms;
use App\Models\Room;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static string|UnitEnum|null $navigationGroup = 'Довідники';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'аудиторія';

    protected static ?string $pluralModelLabel = 'Аудиторії';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('building_id')->label('Корпус')->relationship('building', 'name')->required()->preload(),
            TextInput::make('number')->label('Номер')->required()->maxLength(16),
            Select::make('type')->label('Тип')->options(RoomType::class)->default(RoomType::Classroom)->required(),
            TextInput::make('capacity')->label('Місткість')->numeric()->minValue(1),
            TextInput::make('max_groups')->label('Груп одночасно')->numeric()->minValue(1)->default(1)->required()
                ->helperText('Більше 1 — для спортзалу та інших аудиторій на кілька груп.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('building'))
            ->defaultSort('number')
            ->columns([
                TextColumn::make('label')->label('Аудиторія')->state(fn (Room $r) => $r->label()),
                TextColumn::make('number')->label('Номер')->searchable()->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('building.name')->label('Корпус'),
                TextColumn::make('type')->label('Тип')->badge(),
                TextColumn::make('capacity')->label('Місткість')->sortable(),
                TextColumn::make('max_groups')->label('Груп одночасно'),
            ])
            ->filters([
                SelectFilter::make('building_id')->label('Корпус')->relationship('building', 'name'),
                SelectFilter::make('type')->label('Тип')->options(RoomType::class),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRooms::route('/')];
    }
}
