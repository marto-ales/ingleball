<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
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
     * Groups this account will belong to once created, keyed by group id with
     * the organizer flag each membership gets. It is a shared object because
     * every factory call hands the work to a copy, and the hook that joins the
     * groups has to see the plan as it is at the end of the chain, not as it
     * was where the hook was registered.
     */
    private ?\ArrayObject $plannedMemberships = null;

    private bool $membershipsHooked = false;

    /**
     * @return \ArrayObject<int, bool>
     */
    private function plannedMemberships(): \ArrayObject
    {
        return $this->plannedMemberships ??= new \ArrayObject;
    }

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
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * An account is not tied to a group by itself: creating one adds the
     * memberships and the player profiles that go with them, one set per group.
     *
     * The hook is registered here and not in configure() because every factory
     * method that takes attributes works on a copy, and a closure added in
     * configure() would keep reading the plan of the instance that created it.
     */
    private function hookMemberships(): static
    {
        if ($this->membershipsHooked) {
            return $this;
        }

        // afterCreating() answers with a copy of this factory, and that copy
        // has to know the hook is already in place or create() would keep
        // handing itself to a fresh instance forever.
        $this->membershipsHooked = true;

        return $this->afterCreating(function (User $user): void {
            $memberships = $this->plannedMemberships();

            if (count($memberships) === 0) {
                $memberships[self::defaultGroupId()] = false;
            }

            foreach ($memberships as $groupId => $isOrganizer) {
                GroupMembership::firstOrCreate(
                    ['group_id' => $groupId, 'user_id' => $user->id],
                    ['is_organizer' => $isOrganizer],
                );

                Player::firstOrCreate(
                    ['user_id' => $user->id, 'group_id' => $groupId],
                    ['speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'goalkeeping' => 5],
                );
            }

            $user->unsetRelation('memberships')->unsetRelation('players');
        });
    }

    public function create($attributes = [], ?Model $parent = null)
    {
        $factory = $this->hookMemberships();

        return $factory === $this
            ? parent::create($attributes, $parent)
            : $factory->create($attributes, $parent);
    }

    public function make($attributes = [], ?Model $parent = null)
    {
        $factory = $this->hookMemberships();

        return $factory === $this
            ? parent::make($attributes, $parent)
            : $factory->make($attributes, $parent);
    }

    /**
     * Every `state()` and every `create($attributes)` hands the work to a fresh
     * factory, so the groups this account was asked to join travel along with
     * the rest of the factory state.
     */
    protected function newInstance(array $arguments = [])
    {
        $factory = parent::newInstance($arguments);

        $factory->plannedMemberships = $this->plannedMemberships();
        $factory->membershipsHooked = $this->membershipsHooked;

        return $factory;
    }

    /**
     * Tests get everyone in the same group unless they say otherwise, so the
     * default group is shared rather than created per user.
     */
    public static function defaultGroupId(): int
    {
        return Group::query()->orderBy('id')->value('id') ?? Group::factory()->create()->id;
    }

    public function inGroup(Group|int $group, bool $isOrganizer = false): static
    {
        $groupId = $group instanceof Group ? $group->id : $group;

        $this->plannedMemberships()->exchangeArray([$groupId => $isOrganizer]);

        return $this->hookMemberships();
    }

    /**
     * An account can also belong to several groups at once; the organizer flag
     * is set per membership. The default group stays unless it is replaced.
     */
    public function alsoInGroup(Group|int $group, bool $isOrganizer = false): static
    {
        $plan = $this->plannedMemberships();

        if ($plan->count() === 0) {
            $plan[self::defaultGroupId()] = false;
        }

        $groupId = $group instanceof Group ? $group->id : $group;
        $plan[$groupId] = $isOrganizer;

        return $this->hookMemberships();
    }

    public function organizer(): static
    {
        $plan = $this->plannedMemberships();

        if ($plan->count() === 0) {
            $plan[self::defaultGroupId()] = true;
        } else {
            $plan[array_key_last($plan->getArrayCopy())] = true;
        }

        return $this->hookMemberships();
    }
}
