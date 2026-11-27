<?php

namespace Database\Factories;

use App\Models\IntegrationEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IntegrationEvent> */
class IntegrationEventFactory extends Factory
{
    public function definition(): array
    {
        return ['key' => fake()->unique()->uuid(), 'type' => 'demo.payment', 'payload' => ['status' => 'pending']];
    }
}
