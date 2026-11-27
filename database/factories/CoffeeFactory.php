<?php

namespace Database\Factories;

use App\Models\Coffee;
use App\Models\Roaster;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Coffee> */
class CoffeeFactory extends Factory
{
    public function definition(): array
    {
        return ['roaster_id' => Roaster::factory(), 'slug' => fake()->unique()->slug(), 'name' => 'Voorbeeld Lot '.fake()->unique()->numberBetween(1, 9999), 'country' => 'Ethiopië', 'region' => 'Sidama', 'producer' => 'Demo-coöperatie', 'variety' => 'Heirloom', 'process' => 'Washed', 'roast' => 'Licht', 'method' => 'filter', 'notes' => ['Jasmijn', 'Perzik'], 'description' => 'Fictieve koffie met een bloemig en helder karakter.', 'color' => '#d6a653', 'roast_to_order' => false];
    }
}
