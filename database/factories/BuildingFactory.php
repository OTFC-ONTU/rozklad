<?php

namespace Database\Factories;

use App\Models\Building;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Building> */
class BuildingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => (string) fake()->unique()->numberBetween(1, 99),
            'name' => 'Корпус '.fake()->word(),
        ];
    }
}
