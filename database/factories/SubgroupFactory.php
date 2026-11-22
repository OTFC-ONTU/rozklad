<?php

namespace Database\Factories;

use App\Models\StudentGroup;
use App\Models\Subgroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subgroup> */
class SubgroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'student_group_id' => StudentGroup::factory(),
            'division' => 'Основний',
            'name' => (string) fake()->unique()->numberBetween(1, 999),
            'students_count' => 13,
        ];
    }
}
