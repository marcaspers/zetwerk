<?php

namespace Database\Factories;

use App\Models\CoffeeVariant;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OrderItem> */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return ['order_id' => Order::factory(), 'coffee_variant_id' => CoffeeVariant::factory(), 'name' => 'Voorbeeld Lot', 'grams' => 250, 'quantity' => 1, 'price_cents' => 1650, 'batch_snapshot' => ['status' => 'Nog onbekend — wordt na bestelling gebrand', 'max_roast_days' => 14]];
    }
}
