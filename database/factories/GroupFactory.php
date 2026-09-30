<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'join_code' => Str::upper(Str::random(8)),
            'whatsapp_group' => null,
        ];
    }

    public function withCode(string $code): static
    {
        return $this->state(fn (): array => ['join_code' => $code]);
    }

    /**
     * A group that already has plain players in it.
     */
    public function withMembers(int $count = 1): static
    {
        return $this->afterCreating(function (Group $group) use ($count): void {
            User::factory()->count($count)->inGroup($group)->create();
        });
    }

    /**
     * A group someone runs, with that person as its organizer.
     */
    public function withOrganizer(): static
    {
        return $this->afterCreating(function (Group $group): void {
            User::factory()->inGroup($group)->organizer()->create();
        });
    }
}
