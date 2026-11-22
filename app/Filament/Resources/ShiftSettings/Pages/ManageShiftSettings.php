<?php

namespace App\Filament\Resources\ShiftSettings\Pages;

use App\Filament\Resources\ShiftSettings\ShiftSettingResource;
use App\Models\ShiftSetting;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageShiftSettings extends ManageRecords
{
    protected static string $resource = ShiftSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Новий запис починається з чинних налаштувань: зазвичай змінюють одне поле
            // (вимкнути 2 зміну на час практики, перенести курс).
            CreateAction::make()->fillForm(fn () => [
                ...(ShiftSetting::newest() ?? new ShiftSetting)->only([
                    'second_shift_courses', 'second_shift_enabled',
                    'first_fourth_pair_enabled', 'second_fourth_pair_enabled',
                ]),
                'effective_from' => today()->toDateString(),
            ]),
        ];
    }
}
