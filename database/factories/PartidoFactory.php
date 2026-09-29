<?php

namespace Database\Factories;

use App\Models\Partido;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Partido>
 */
class PartidoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'played_at' => fake()->dateTimeBetween('+2 days', '+21 days'),
            'venue' => fake()->streetAddress(),
            'size' => 5,
            'status' => 'open',
            'group_id' => UserFactory::defaultGroupId(),
            'created_by' => User::factory(),
        ];
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn () => [
            'created_by' => $user->id,
            'group_id' => $user->group_id,
        ]);
    }

    public function inGroup(int $groupId): static
    {
        return $this->state(fn (): array => ['group_id' => $groupId]);
    }
}
