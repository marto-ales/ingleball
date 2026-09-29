<?php

namespace Database\Factories;

use App\Models\Group;
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
}
