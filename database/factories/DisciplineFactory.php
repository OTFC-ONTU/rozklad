<?php

namespace Database\Factories;

use App\Models\Discipline;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Discipline> */
class DisciplineFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(3, true));

        return [
            'name' => $name,
            'short_name' => mb_substr($name, 0, 12),
            'room_type' => null,
        ];
    }
}
