<?php

namespace App\Filament\Resources\StudentGroups\Pages;

use App\Filament\Resources\StudentGroups\StudentGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStudentGroups extends ManageRecords
{
    protected static string $resource = StudentGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
