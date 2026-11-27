<?php

namespace Database\Factories;

use App\Models\Coffee;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Recipe> */
class RecipeFactory extends Factory
{
    public function definition(): array
    {
        return ['coffee_id' => Coffee::factory(), 'user_id' => User::factory(), 'title' => 'Een heldere ochtend-V60', 'method' => 'V60', 'dose' => 15, 'water' => 250, 'temperature' => 94, 'seconds' => 180, 'grinder' => 'Comandante C40', 'grind' => '24 kliks', 'bloom' => 45, 'steps' => "1. Spoel het filter.\n2. Bloom met 45 g water, 40 seconden.\n3. Schenk op tot 150 en daarna 250 g."];
    }
}
