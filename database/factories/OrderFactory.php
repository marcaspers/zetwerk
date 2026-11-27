<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Roaster;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return ['checkout_key' => fake()->unique()->uuid(), 'user_id' => User::factory(), 'roaster_id' => Roaster::factory(), 'number' => 'ZW-'.fake()->unique()->numerify('##########'), 'currency' => 'EUR', 'subtotal_cents' => 1650, 'shipping_cents' => 495, 'total_cents' => 2145, 'address' => ['name' => 'Demo Gebruiker', 'street' => 'Teststraat 1', 'postcode' => '1234 AB', 'city' => 'Utrecht'], 'promise' => ['roaster' => 'Demo-roaster', 'shipping' => 'Binnen 1–2 werkdagen', 'max_roast_days' => 14, 'demo' => true], 'reserved_until' => now()->addMinutes(15)];
    }
}
