<?php

namespace App\Filament\Resources\SlotSettings\Pages;

use App\Filament\Resources\SlotSettings\SlotSettingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSlotSettings extends ManageRecords
{
    protected static string $resource = SlotSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
