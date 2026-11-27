<?php

namespace Database\Factories;

use App\Models\Coffee;
use App\Models\RoastBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RoastBatch> */
class RoastBatchFactory extends Factory
{
    public function definition(): array
    {
        return ['coffee_id' => Coffee::factory(), 'code' => 'DEMO-'.fake()->unique()->uuid(), 'roasted_at' => today()->subDays(5), 'stock' => 40];
    }
}
