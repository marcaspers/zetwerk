<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Report> */
class ReportFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory(), 'kind' => 'recipe', 'target_id' => Recipe::factory(), 'reason' => 'Ongepaste tekst in dit voorbeeld.', 'status' => 'open'];
    }
}
