<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Player>
 */
class PlayerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'speed' => fake()->numberBetween(3, 9),
            'skill' => fake()->numberBetween(3, 9),
            'passing' => fake()->numberBetween(3, 9),
            'shooting' => fake()->numberBetween(3, 9),
            'defense' => fake()->numberBetween(3, 9),
            'overall' => fake()->numberBetween(4, 9),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }
}
