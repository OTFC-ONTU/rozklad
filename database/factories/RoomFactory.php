<?php

namespace Database\Factories;

use App\Enums\RoomType;
use App\Models\Building;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Room> */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'building_id' => Building::factory(),
            'number' => (string) fake()->unique()->numberBetween(100, 499),
            'type' => RoomType::Classroom,
            'capacity' => 30,
            'max_groups' => 1,
        ];
    }

    public function gym(): static
    {
        return $this->state(['type' => RoomType::Gym, 'capacity' => 90, 'max_groups' => 3]);
    }
}
