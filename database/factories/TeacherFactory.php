<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Teacher> */
class TeacherFactory extends Factory
{
    public function definition(): array
    {
        $last = fake()->unique()->lastName();

        return [
            'full_name' => "{$last} ".fake()->firstName().' '.fake()->firstName(),
            'short_name' => "{$last} ".fake()->randomLetter().'.'.fake()->randomLetter().'.',
        ];
    }
}
