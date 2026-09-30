<?php

namespace Database\Factories;

use App\Models\Guest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guest>
 */
class GuestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'speed' => fake()->numberBetween(4, 8),
            'skill' => fake()->numberBetween(4, 8),
            'passing' => fake()->numberBetween(4, 8),
            'shooting' => fake()->numberBetween(4, 8),
            'defense' => fake()->numberBetween(4, 8),
            'overall' => fake()->numberBetween(5, 8),
        ];
    }
}
