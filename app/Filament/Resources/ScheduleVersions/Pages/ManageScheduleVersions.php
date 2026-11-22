<?php

namespace App\Filament\Resources\ScheduleVersions\Pages;

use App\Filament\Resources\ScheduleVersions\ScheduleVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageScheduleVersions extends ManageRecords
{
    protected static string $resource = ScheduleVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
