<?php

namespace App\Filament\Resources\SlotSettings;

use App\Enums\Shift;
use App\Filament\Resources\SlotSettings\Pages\ManageSlotSettings;
use App\Models\SlotSetting;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SlotSettingResource extends Resource
{
    protected static ?string $model = SlotSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Налаштування';

    protected static ?string $modelLabel = 'налаштування слотів';

    protected static ?string $pluralModelLabel = 'Слоти змін';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('shift')->label('Зміна')->options(Shift::class)->required(),
            DatePicker::make('effective_from')->label('Діє з')->required(),
            Toggle::make('saturday_enabled')->label('Субота увімкнена'),
            Toggle::make('fourth_pair_enabled')->label('4-а пара увімкнена')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->description('Без записів: субота вимкнена, 4-а пара увімкнена. Діє останній запис зміни на дату.')
            ->columns([
                TextColumn::make('shift')->label('Зміна')->badge(),
                TextColumn::make('effective_from')->label('Діє з')->date('d.m.Y')->sortable(),
                IconColumn::make('saturday_enabled')->label('Субота')->boolean(),
                IconColumn::make('fourth_pair_enabled')->label('4-а пара')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSlotSettings::route('/')];
    }
}
