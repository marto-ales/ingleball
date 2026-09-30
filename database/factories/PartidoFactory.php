<?php

namespace Database\Factories;

use App\Models\Group;
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

    /**
     * The match belongs to the group of the person who created it: one account
     * may play in several groups, and each match stays in one of them.
     */
    public function createdBy(User $user, Group|int|null $group = null): static
    {
        return $this->state(function () use ($user, $group): array {
            $groupId = $group instanceof Group
                ? $group->id
                : ($group ?? $user->memberships()->orderBy('group_id')->value('group_id'));

            return [
                'created_by' => $user->id,
                'group_id' => $groupId,
            ];
        });
    }

    public function inGroup(int $groupId): static
    {
        return $this->state(fn (): array => ['group_id' => $groupId]);
    }
}
