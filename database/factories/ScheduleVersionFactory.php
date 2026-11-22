<?php

namespace Database\Factories;

use App\Models\ScheduleVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ScheduleVersion> */
class ScheduleVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Розклад І семестру',
            'effective_from' => '2026-09-01',
        ];
    }
}
