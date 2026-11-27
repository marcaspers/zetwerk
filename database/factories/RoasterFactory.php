<?php

namespace Database\Factories;

use App\Models\Roaster;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Roaster> */
class RoasterFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->company(), 'slug' => fake()->unique()->slug(), 'city' => 'Utrecht', 'story' => 'Een fictieve onafhankelijke branderij met aandacht voor herkomst.', 'color' => '#315447', 'max_roast_days' => 14, 'shipping_cents' => 495, 'shipping_policy' => 'Verzonden binnen 1–2 werkdagen'];
    }
}
