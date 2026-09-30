<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Partido;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_organizer_is_forbidden_from_user_management(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('users.manage.index'))
            ->assertForbidden();
    }

    public function test_organizer_can_list_users(): void
    {
        $organizer = User::factory()->organizer()->create();
        User::factory()->count(3)->create();

        $this->actingAs($organizer)
            ->get(route('users.manage.index'))
            ->assertOk()
            ->assertSee('Jugadores');
    }

    public function test_organizer_can_create_a_managed_player(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)->post(route('users.manage.store'), [
            'name' => 'Pancho',
            'phone' => '+5491155550088',
        ])->assertRedirect(route('users.manage.index'));

        $this->assertDatabaseHas('users', ['name' => 'Pancho', 'is_managed' => true]);
        $this->assertDatabaseHas('players', ['user_id' => User::where('name', 'Pancho')->first()->id]);
    }

    public function test_managed_player_cannot_login(): void
    {
        $managed = User::factory()->create(['is_managed' => true, 'password' => bcrypt(Str::random(32))]);

        $this->post('/login', ['identity' => $managed->username, 'password' => 'nadie_sabe_esto']);

        $this->assertGuest();
    }

    public function test_banned_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'username' => 'marta',
            'password' => Hash::make('secret123'),
        ]);

        $user->memberships()->update(['banned_at' => now()]);

        $this->from('/login')->post('/login', ['identity' => 'marta', 'password' => 'secret123'])
            ->assertSessionHasErrors('identity');

        $this->assertGuest();
    }

    public function test_a_block_in_one_group_does_not_stop_the_login_for_the_others(): void
    {
        $other = Group::factory()->create();

        $user = User::factory()
            ->alsoInGroup($other)
            ->alsoInGroup(UserFactory::defaultGroupId())
            ->create(['username' => 'marta', 'password' => Hash::make('secret123')]);

        $user->membershipIn(UserFactory::defaultGroupId())->update(['banned_at' => now()]);

        $this->post('/login', ['identity' => 'marta', 'password' => 'secret123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_organizer_can_block_unblock_and_expel_users(): void
    {
        $organizer = User::factory()->organizer()->create();
        $target = User::factory()->create();

        $this->actingAs($organizer)
            ->post(route('users.manage.block', $target))
            ->assertRedirect();

        $this->assertTrue($target->refresh()->isBannedIn(UserFactory::defaultGroupId()));

        $this->actingAs($organizer)
            ->post(route('users.manage.unblock', $target))
            ->assertRedirect();

        $this->assertFalse($target->refresh()->isBannedIn(UserFactory::defaultGroupId()));

        $this->actingAs($organizer)
            ->post(route('users.manage.expel', $target))
            ->assertRedirect();

        // The account and its login survive: only the membership is gone.
        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseMissing('group_user', [
            'user_id' => $target->id,
            'group_id' => UserFactory::defaultGroupId(),
        ]);
        $this->assertDatabaseMissing('players', [
            'user_id' => $target->id,
            'group_id' => UserFactory::defaultGroupId(),
        ]);
    }

    public function test_expelling_a_member_does_not_touch_their_other_groups(): void
    {
        $other = Group::factory()->create();

        $organizer = User::factory()->organizer()->create();
        $target = User::factory()->alsoInGroup($other)->alsoInGroup(UserFactory::defaultGroupId())->create();

        $this->actingAs($organizer)
            ->post(route('users.manage.expel', $target))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseHas('group_user', ['user_id' => $target->id, 'group_id' => $other->id]);
        $this->assertDatabaseHas('players', ['user_id' => $target->id, 'group_id' => $other->id]);
    }

    public function test_organizer_cannot_remove_themselves(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)
            ->post(route('users.manage.expel', $organizer))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $organizer->id]);
        $this->assertDatabaseHas('group_user', [
            'user_id' => $organizer->id,
            'group_id' => UserFactory::defaultGroupId(),
        ]);
    }

    public function test_user_list_shows_self_evaluation_status_and_evaluate_button(): void
    {
        $organizer = User::factory()->organizer()->create();
        $without = User::factory()->create();
        $without->playerFor(UserFactory::defaultGroupId())->update([
            'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'goalkeeping' => 5,
        ]);
        $with = User::factory()->create();
        $with->playerFor(UserFactory::defaultGroupId())->update([
            'speed' => 6, 'skill' => 6, 'passing' => 6, 'shooting' => 6, 'defense' => 6, 'goalkeeping' => 7,
            'self_eval_completed_at' => now(),
        ]);
        $organizer->evaluationsGiven()->create([
            'rated_user_id' => $with->id,
            'group_id' => UserFactory::defaultGroupId(),
            'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'goalkeeping' => 5,
        ]);

        $this->actingAs($organizer)
            ->get(route('users.manage.index'))
            ->assertOk()
            ->assertSee('Autoevaluación')
            ->assertSee('<span class="muted">No</span>', false)
            ->assertSee('>Sí</span>', false)
            ->assertSee('Eval. org.')
            ->assertSee('<a class="btn btn-sm btn-ghost" href="'.route('evaluation.edit', $with).'">Evaluar</a>', false)
            ->assertSee('<a class="btn btn-sm btn-primary" href="'.route('evaluation.edit', $without).'">Evaluar</a>', false);
    }

    public function test_saving_profile_marks_self_evaluation_as_completed(): void
    {
        $user = User::factory()->create();
        $user->playerFor(UserFactory::defaultGroupId())->update([
            'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'goalkeeping' => 5,
        ]);

        $this->actingAs($user)->patch('/profile', [
            'name' => $user->name,
            'email' => null,
            'phone' => null,
            'likes_goalie' => 1,
            'speed' => 6, 'skill' => 6, 'passing' => 6, 'shooting' => 6, 'defense' => 6, 'goalkeeping' => 7,
        ]);

        $this->assertNotNull($user->playerFor(UserFactory::defaultGroupId())->fresh()->self_eval_completed_at);
    }

    public function test_registration_adopts_managed_user_and_keeps_player_history(): void
    {
        $managed = User::factory()->create(['name' => 'Pancho', 'username' => 'pancho', 'is_managed' => true]);
        $managed->playerFor(UserFactory::defaultGroupId())->update([
            'speed' => 6, 'skill' => 6, 'passing' => 6, 'shooting' => 6, 'defense' => 5,
        ]);

        $match = Partido::factory()->createdBy(User::factory()->organizer()->create())->create(['played_at' => now()->addDay()]);
        $match->entries()->create(['user_id' => $managed->id, 'role' => 'going']);

        $this->post('/register', [
            'name' => 'Pancho Real',
            'username' => 'pancho',
            'email' => 'pancho@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'group_code' => Group::query()->orderBy('id')->value('join_code'),
        ])->assertRedirect(route('dashboard'));

        $managed->refresh();

        $this->assertAuthenticatedAs($managed);
        $this->assertFalse($managed->is_managed);
        $this->assertSame('Pancho Real', $managed->name);
        $this->assertNotNull($managed->playerFor(UserFactory::defaultGroupId()));
        $this->assertDatabaseHas('match_entries', ['match_id' => $match->id, 'user_id' => $managed->id]);
    }

    public function test_registration_keeps_username_unique_for_real_users(): void
    {
        User::factory()->create(['username' => 'marta']);

        $this->post('/register', [
            'name' => 'Otra',
            'username' => 'marta',
            'email' => 'otra@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'group_code' => Group::query()->orderBy('id')->value('join_code'),
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }
}
