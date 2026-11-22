<?php

namespace App\Filament\Resources\GroupActivities\Pages;

use App\Filament\Resources\GroupActivities\GroupActivityResource;
use App\Models\GroupActivity;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ManageGroupActivities extends ManageRecords
{
    protected static string $resource = GroupActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Один період одразу для кількох груп (наприклад, сесія всього курсу).
            CreateAction::make()->using(fn (array $data) => DB::transaction(function () use ($data) {
                $attributes = Arr::except($data, 'student_group_ids');
                $created = null;

                foreach ($data['student_group_ids'] as $groupId) {
                    $created = GroupActivity::create([...$attributes, 'student_group_id' => $groupId]);
                }

                return $created;
            })),
        ];
    }
}
