<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'group_id' => self::defaultGroupId(),
            'is_organizer' => false,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Tests get everyone in the same group unless they say otherwise, so the
     * default group is shared rather than created per user.
     */
    public static function defaultGroupId(): int
    {
        return Group::query()->orderBy('id')->value('id') ?? Group::factory()->create()->id;
    }

    public function inGroup(Group $group): static
    {
        return $this->state(fn (): array => ['group_id' => $group->id]);
    }

    public function organizer(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_organizer' => true,
        ]);
    }
}
