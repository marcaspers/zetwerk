<?php

namespace Database\Factories;

use App\Models\Coffee;
use App\Models\CoffeeVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CoffeeVariant> */
class CoffeeVariantFactory extends Factory
{
    public function definition(): array
    {
        return ['coffee_id' => Coffee::factory(), 'grams' => 250, 'price_cents' => 1650, 'stock' => 20];
    }
}
