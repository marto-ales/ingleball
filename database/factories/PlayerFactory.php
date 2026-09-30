<?php

namespace Database\Factories;

use App\Models\Group;
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
            'likes_goalie' => false,
            'goalkeeping' => fake()->numberBetween(3, 9),
        ];
    }

    /**
     * A profile belongs to an account inside one group: the same person has
     * one of these per group they play in.
     */
    public function forUser(User $user, Group|int|null $group = null): static
    {
        $groupId = match (true) {
            $group instanceof Group => $group->id,
            is_int($group) => $group,
            default => (int) ($user->memberships->first()?->group_id ?? UserFactory::defaultGroupId()),
        };

        return $this->state(fn () => [
            'user_id' => $user->id,
            'group_id' => $groupId,
        ]);
    }
}
