<?php

namespace App\Filament\Resources\PracticeTypes\Pages;

use App\Filament\Resources\PracticeTypes\PracticeTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePracticeTypes extends ManageRecords
{
    protected static string $resource = PracticeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
