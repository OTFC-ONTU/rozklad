<?php

namespace App\Filament\Resources\ShiftSettings;

use App\Enums\Shift;
use App\Filament\Resources\ShiftSettings\Pages\ManageShiftSettings;
use App\Models\ShiftSetting;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ShiftSettingResource extends Resource
{
    protected static ?string $model = ShiftSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Налаштування';

    protected static ?string $modelLabel = 'налаштування змін';

    protected static ?string $pluralModelLabel = 'Зміни';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        $courses = array_combine(ShiftSetting::COURSES, array_map(fn (int $c) => "{$c} курс", ShiftSetting::COURSES));

        return $schema->columns(2)->components([
            DatePicker::make('effective_from')->label('Діє з')->required()
                ->unique(ignoreRecord: true),
            ToggleButtons::make('second_shift_courses')->label('Курси 2 зміни')
                ->helperText('Решта курсів навчаються в 1 зміну.')
                ->options($courses)->multiple()->inline()->default([]),
            Toggle::make('second_shift_enabled')->label('2 зміна працює')->default(true)
                ->columnSpanFull()
                ->helperText('Вимкніть, коли всі групи тимчасово навчаються в 1 зміну (напр., 4 курс на практиці). '
                    .'Тижневий розклад при цьому не змінюється.'),
            Toggle::make('first_fourth_pair_enabled')->label('4-а пара в 1 зміні')->default(true),
            Toggle::make('second_fourth_pair_enabled')->label('4-а пара в 2 зміні')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        $courses = fn (Shift $shift) => fn (ShiftSetting $r) => implode(', ', $r->coursesOf($shift)) ?: '—';

        return $table
            ->defaultSort('effective_from', 'desc')
            ->description('Змін завжди дві; діє останній запис на дату. Без записів усі курси в 1 зміні, 4-а пара увімкнена. Навчальні суботи — у семестрі.')
            ->columns([
                TextColumn::make('effective_from')->label('Діє з')->date('d.m.Y')->sortable(),
                TextColumn::make('first_courses')->label('Курси 1 зміни')->state($courses(Shift::First)),
                TextColumn::make('second_courses')->label('Курси 2 зміни')->state($courses(Shift::Second)),
                IconColumn::make('second_shift_enabled')->label('2 зміна працює')->boolean(),
                IconColumn::make('first_fourth_pair_enabled')->label('4-а пара (1)')->boolean(),
                IconColumn::make('second_fourth_pair_enabled')->label('4-а пара (2)')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageShiftSettings::route('/')];
    }
}
