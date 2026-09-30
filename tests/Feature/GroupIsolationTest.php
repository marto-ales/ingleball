<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\Guest;
use App\Models\Partido;
use App\Models\User;
use App\Services\AlgorithmSettings;
use App\Services\Scorer;
use App\Support\ActiveGroup;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Groups are isolated: nothing of one group is reachable from another, no
 * matter which id a request carries. One account may be in several of them,
 * and each group keeps its own profile, ratings, evaluations and settings.
 */
final class GroupIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function otherGroup(): Group
    {
        return Group::factory()->withCode('OTHERGRP')->create();
    }

    private function defaultGroup(): Group
    {
        return Group::findOrFail(UserFactory::defaultGroupId());
    }

    /**
     * Work in a group for the rest of the test, as the topbar selector does.
     */
    private function useGroup(User $user, Group $group): self
    {
        $this->withSession([ActiveGroup::SESSION_KEY => $group->id]);
        app(ActiveGroup::class)->invalidate();

        return $this;
    }

    public function test_dashboard_only_lists_matches_of_the_group_in_use(): void
    {
        $other = $this->otherGroup();
        $mine = User::factory()->alsoInGroup($other)->create();

        Partido::factory()->createdBy($mine)->create(['title' => 'Partido mío']);
        Partido::factory()->inGroup($other->id)->create(['title' => 'Partido ajeno']);

        $this->useGroup($mine, $this->defaultGroup());

        $this->actingAs($mine)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Partido mío')
            ->assertDontSee('Partido ajeno');

        $this->useGroup($mine, $other);

        $this->actingAs($mine)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Partido ajeno')
            ->assertDontSee('Partido mío');
    }

    public function test_the_same_account_has_one_profile_per_group(): void
    {
        $other = $this->otherGroup();
        $user = User::factory()->alsoInGroup($other)->create();

        $mine = $user->playerFor(UserFactory::defaultGroupId());
        $theirs = $user->playerFor($other->id);

        $this->assertNotSame($mine->id, $theirs->id);

        $mine->update(['speed' => 3]);
        $theirs->update(['speed' => 9]);

        $this->assertSame(3, (int) $user->playerFor(UserFactory::defaultGroupId())->speed);
        $this->assertSame(9, (int) $user->playerFor($other->id)->speed);
    }

    public function test_a_match_of_another_group_is_not_reachable(): void
    {
        $mine = User::factory()->organizer()->create();
        $other = $this->otherGroup();
        $match = Partido::factory()->inGroup($other->id)->create();

        $this->actingAs($mine)->get(route('matches.show', $match))->assertNotFound();
        $this->actingAs($mine)->patch(route('matches.lock', $match))->assertNotFound();
    }

    public function test_organizer_cannot_add_a_player_from_another_group_to_the_list(): void
    {
        $organizer = User::factory()->organizer()->create();
        $match = Partido::factory()->createdBy($organizer)->create();

        $stranger = User::factory()->inGroup($this->otherGroup())->create();

        $this->actingAs($organizer)
            ->post(route('entries.manage', $match), [
                'user_id' => $stranger->id,
                'role' => 'going',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('match_entries', ['match_id' => $match->id, 'user_id' => $stranger->id]);
    }

    public function test_user_management_only_lists_own_group(): void
    {
        $organizer = User::factory()->organizer()->create();
        $stranger = User::factory()->inGroup($this->otherGroup())->create(['name' => 'Intruso']);

        $this->actingAs($organizer)
            ->get(route('users.manage.index'))
            ->assertOk()
            ->assertDontSee('Intruso');

        $this->actingAs($organizer)
            ->patch(route('users.manage.update', $stranger), ['name' => 'Intruso', 'phone' => null])
            ->assertNotFound();
    }

    public function test_stats_profiles_are_scoped_to_the_group(): void
    {
        $mine = User::factory()->create(['name' => 'Mío']);
        $stranger = User::factory()->inGroup($this->otherGroup())->create(['name' => 'Ajeno']);

        $this->actingAs($mine)
            ->get(route('stats.index'))
            ->assertOk()
            ->assertSee('Mío')
            ->assertDontSee('Ajeno');

        $this->actingAs($mine)
            ->get(route('stats.show', $stranger))
            ->assertNotFound();
    }

    public function test_a_guest_with_the_same_phone_is_a_separate_record_per_group(): void
    {
        $organizer = User::factory()->organizer()->create();
        $mine = Partido::factory()->createdBy($organizer)->create();

        $other = $this->otherGroup();
        $otherOrganizer = User::factory()->inGroup($other)->organizer()->create();
        $theirs = Partido::factory()->inGroup($other->id)->create();

        $payload = [
            'name' => 'Cheto', 'phone' => '+54 9 11 5555 9999',
            'speed' => 6, 'skill' => 6, 'passing' => 6, 'shooting' => 6, 'defense' => 6, 'overall' => 6,
        ];

        $this->actingAs($organizer)->post(route('guests.store', $mine), $payload);
        $this->actingAs($otherOrganizer)->post(route('guests.store', $theirs), $payload);

        $this->assertSame(2, Guest::where('phone', '+54 9 11 5555 9999')->count());
    }

    public function test_algorithm_settings_are_per_group(): void
    {
        $organizer = User::factory()->organizer()->create();
        $other = $this->otherGroup();
        $otherOrganizer = User::factory()->inGroup($other)->organizer()->create();

        $order = ['defense', 'speed', 'skill', 'passing', 'shooting'];

        $this->actingAs($organizer)->patch('/algorithm', [
            'order' => $order,
            'self_weight' => 0.5,
        ])->assertRedirect();

        $this->actingAs($otherOrganizer)->patch('/algorithm', [
            'order' => ['skill', 'speed', 'passing', 'shooting', 'defense'],
            'self_weight' => 0.5,
        ])->assertRedirect();

        $mineId = $organizer->memberships()->value('group_id');

        $this->assertSame('defense', (new AlgorithmSettings($mineId))->order()[0]);
        $this->assertSame('skill', (new AlgorithmSettings($other->id))->order()[0]);
    }

    public function test_organizer_rotates_the_join_code_and_the_old_one_stops_working(): void
    {
        $organizer = User::factory()->organizer()->create();
        $group = Group::findOrFail($organizer->memberships()->value('group_id'));
        $old = $group->join_code;

        $this->actingAs($organizer)->post(route('groups.code'))->assertRedirect();

        $new = $group->fresh()->join_code;
        $this->assertNotSame($old, $new);

        $this->post('/logout');

        $this->post('/register', [
            'name' => 'Marta', 'username' => 'marta', 'email' => 'marta@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'group_code' => $old,
        ])->assertSessionHasErrors('group_code');

        $this->post('/register', [
            'name' => 'Marta', 'username' => 'marta', 'email' => 'marta@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'group_code' => $new,
        ])->assertRedirect(route('dashboard'));
    }

    public function test_only_the_console_creates_groups(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)
            ->post('/grupo', ['name' => 'Barrio Norte'])
            ->assertStatus(405);

        $this->assertDatabaseMissing('groups', ['name' => 'Barrio Norte']);

        $this->actingAs($organizer)
            ->get(route('groups.show'))
            ->assertOk()
            ->assertDontSee('Crear otro grupo');
    }

    public function test_players_cannot_change_the_group(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player)->patch(route('groups.update'), ['name' => 'Mío'])->assertForbidden();
        $this->actingAs($player)->post('/grupo', ['name' => 'Mío'])->assertStatus(405);
        $this->actingAs($player)->post('/grupo/codigo')->assertForbidden();
    }

    public function test_the_group_name_leads_the_shared_match_message(): void
    {
        $other = $this->otherGroup();
        $organizer = User::factory()->inGroup($other)->organizer()->create();
        $match = Partido::factory()->inGroup($other->id)->create(['title' => 'Clásico']);

        $this->actingAs($organizer)
            ->patch(route('entries.update', $match), ['role' => 'going'])
            ->assertSessionHas('attendance.message', fn (string $message) => str_contains($message, $other->name)
                && str_contains($message, 'Clásico'));
    }

    public function test_evaluations_of_another_group_do_not_weigh_on_the_profile(): void
    {
        $mineId = UserFactory::defaultGroupId();
        $mine = User::factory()->create();

        $mine->playerFor($mineId)->update([
            'speed' => 4, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'goalkeeping' => 5,
        ]);

        $other = $this->otherGroup();
        $otherOrganizer = User::factory()->inGroup($other)->organizer()->create();

        $otherOrganizer->evaluationsGiven()->create([
            'rated_user_id' => $mine->id,
            'group_id' => $other->id,
            'speed' => 10, 'skill' => 10, 'passing' => 10, 'shooting' => 10, 'defense' => 10, 'goalkeeping' => 10,
        ]);

        $this->assertSame(4.0, app(Scorer::class)->forGroup($mineId)->attributesForUser($mine)['speed']);
    }

    public function test_a_managed_player_can_only_be_claimed_with_the_code_of_their_group(): void
    {
        $organizer = User::factory()->organizer()->create();
        $group = Group::findOrFail($organizer->memberships()->value('group_id'));
        $managed = User::factory()->inGroup($group)->create([
            'username' => 'cheto',
            'is_managed' => true,
        ]);

        $other = $this->otherGroup();

        $this->post('/register', [
            'name' => 'Cheto', 'username' => 'cheto', 'email' => 'cheto@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'group_code' => $other->join_code,
        ])->assertSessionHasErrors('group_code');

        $this->assertTrue($managed->refresh()->is_managed);
        $this->assertTrue($managed->isMemberOf($group->id));
        $this->assertFalse($managed->isMemberOf($other->id));

        $this->post('/register', [
            'name' => 'Cheto', 'username' => 'cheto', 'email' => 'cheto@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'group_code' => $group->join_code,
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($managed->refresh()->is_managed);
    }

    /**
     * The rules that only make sense with more than one group per account.
     */
    public function test_the_selector_switches_the_group_in_use(): void
    {
        $other = $this->otherGroup();
        $user = User::factory()->alsoInGroup($other)->create();

        $this->actingAs($user)
            ->get(route('groups.show'))
            ->assertOk()
            ->assertSee($other->name)
            ->assertSee('Tus grupos');

        $this->actingAs($user)
            ->post(route('groups.activate'), ['group' => $other->id])
            ->assertRedirect(route('dashboard'));

        $this->assertSame($other->id, app(ActiveGroup::class)->id());

        $this->actingAs($user)
            ->post(route('groups.activate'), ['group' => 999999])
            ->assertForbidden();
    }

    public function test_a_group_the_account_does_not_belong_to_cannot_be_activated(): void
    {
        $stranger = $this->otherGroup();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('groups.activate'), ['group' => $stranger->id])
            ->assertForbidden();
    }

    public function test_organizing_a_group_does_not_organize_the_others(): void
    {
        $other = $this->otherGroup();
        $organizer = User::factory()->alsoInGroup($other)->organizer()->create();
        $mineId = $organizer->memberships()->where('group_id', '!=', $other->id)->value('group_id');

        $this->assertTrue($organizer->isOrganizerIn($other->id));
        $this->assertFalse($organizer->isOrganizerIn($mineId));

        $this->useGroup($organizer, $other);

        $this->actingAs($organizer)
            ->patch(route('groups.update'), ['name' => 'Renombrado'])
            ->assertRedirect();

        $this->assertSame('Renombrado', Group::findOrFail($other->id)->name);
        $this->assertNotSame('Renombrado', Group::findOrFail($mineId)->name);
    }

    public function test_a_block_applies_to_one_group_only(): void
    {
        $other = $this->otherGroup();
        $organizer = User::factory()->organizer()->create();
        $target = User::factory()->alsoInGroup($other)->create();

        $this->actingAs($organizer)->post(route('users.manage.block', $target))->assertRedirect();

        $this->assertTrue($target->refresh()->isBannedIn(UserFactory::defaultGroupId()));
        $this->assertFalse($target->isBannedIn($other->id));

        // The person cannot pick the group they were blocked in...
        $this->actingAs($target)
            ->post(route('groups.activate'), ['group' => UserFactory::defaultGroupId()])
            ->assertForbidden();

        // ...but the other one is still theirs to use.
        $this->actingAs($target)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_leaving_a_group_keeps_the_account_and_the_other_groups(): void
    {
        $other = $this->otherGroup();
        $user = User::factory()->alsoInGroup($other)->create();
        $mineId = $user->memberships()->where('group_id', '!=', $other->id)->value('group_id');

        $user->evaluationsGiven()->create([
            'rated_user_id' => $user->id,
            'group_id' => $mineId,
            'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'goalkeeping' => 5,
        ]);

        $this->useGroup($user, $this->defaultGroup());

        $this->actingAs($user)->post(route('groups.leave'))->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('group_user', ['user_id' => $user->id, 'group_id' => $mineId]);
        $this->assertDatabaseMissing('players', ['user_id' => $user->id, 'group_id' => $mineId]);
        $this->assertDatabaseMissing('player_evaluations', ['organizer_user_id' => $user->id, 'group_id' => $mineId]);

        $this->assertTrue($user->refresh()->isMemberOf($other->id));
        $this->assertNotNull($user->playerFor($other->id));
    }

    public function test_an_organizer_cannot_leave_the_group_they_run(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)
            ->post(route('groups.leave'))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertTrue($organizer->refresh()->isMemberOf(UserFactory::defaultGroupId()));
    }

    public function test_joining_with_a_code_adds_a_group_and_keeps_the_rest(): void
    {
        $other = $this->otherGroup();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('groups.join'), ['group_code' => $other->join_code])
            ->assertRedirect();

        $user->refresh();

        $this->assertTrue($user->isMemberOf($other->id));
        $this->assertTrue($user->isMemberOf(UserFactory::defaultGroupId()));
        $this->assertNotNull($user->playerFor($other->id));
        $this->assertSame(2, GroupMembership::where('user_id', $user->id)->count());
    }

    public function test_an_account_without_groups_is_sent_to_join_one(): void
    {
        $user = User::factory()->inGroup($this->otherGroup())->create();
        $user->memberships()->delete();
        $user->players()->delete();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('groups.show'));

        $this->actingAs($user)
            ->get(route('groups.show'))
            ->assertOk()
            ->assertSee('Todavía no tenés grupo');
    }
}
