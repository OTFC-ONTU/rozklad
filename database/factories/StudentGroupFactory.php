<?php

namespace Database\Factories;

use App\Models\StudentGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StudentGroup> */
class StudentGroupFactory extends Factory
{
    public function definition(): array
    {
        $course = fake()->numberBetween(1, 4);

        return [
            'code' => $course.fake()->randomElement(['КГ', 'КБ', 'РП', 'ЕМ']).'-'.fake()->unique()->numberBetween(10, 99),
            'course' => $course,
            'students_count' => 25,
        ];
    }
}
