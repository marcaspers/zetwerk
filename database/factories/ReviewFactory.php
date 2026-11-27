<?php

namespace Database\Factories;

use App\Models\Coffee;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Review> */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return ['coffee_id' => Coffee::factory(), 'user_id' => User::factory(), 'rating' => 4, 'method' => 'V60', 'body' => 'Een mooie zoete koffie met een frisse afdronk.'];
    }
}
