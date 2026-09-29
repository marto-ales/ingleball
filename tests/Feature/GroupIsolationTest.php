<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Guest;
use App\Models\Partido;
use App\Models\PlayerEvaluation;
use App\Models\User;
use App\Services\AlgorithmSettings;
use App\Services\Scorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Groups are isolated: nothing of one group is reachable from another, no
 * matter which id a request carries.
 */
final class GroupIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function otherGroup(): Group
    {
        return Group::factory()->withCode('OTHERGRP')->create();
    }

    public function test_dashboard_only_lists_own_group_matches(): void
    {
        $mine = User::factory()->create();
        $other = $this->otherGroup();

        Partido::factory()->createdBy($mine)->create(['title' => 'Partido mío']);
        Partido::factory()->inGroup($other->id)->create(['title' => 'Partido ajeno']);

        $this->actingAs($mine)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Partido mío')
            ->assertDontSee('Partido ajeno');
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
        $otherOrganizer = User::factory()->organizer()->inGroup($other)->create();
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
        $otherOrganizer = User::factory()->organizer()->inGroup($other)->create();

        $order = ['defense', 'speed', 'skill', 'passing', 'shooting'];

        $this->actingAs($organizer)->patch('/algorithm', [
            'order' => $order,
            'self_weight' => 0.5,
        ])->assertRedirect();

        $this->actingAs($otherOrganizer)->patch('/algorithm', [
            'order' => ['skill', 'speed', 'passing', 'shooting', 'defense'],
            'self_weight' => 0.5,
        ])->assertRedirect();

        $this->assertSame('defense', (new AlgorithmSettings($organizer->group_id))->order()[0]);
        $this->assertSame('skill', (new AlgorithmSettings($other->id))->order()[0]);
    }

    public function test_organizer_rotates_the_join_code_and_the_old_one_stops_working(): void
    {
        $organizer = User::factory()->organizer()->create();
        $old = $organizer->group->join_code;

        $this->actingAs($organizer)->post(route('groups.code'))->assertRedirect();

        $new = $organizer->group->fresh()->join_code;
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

    public function test_organizer_creates_another_group_and_moves_into_it(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)
            ->post(route('groups.store'), ['name' => 'Barrio Norte'])
            ->assertRedirect(route('groups.show'));

        $organizer->refresh();
        $new = $organizer->group;

        $this->assertSame('Barrio Norte', $new->name);
        $this->assertNotNull($new->join_code);

        $this->assertDatabaseHas('users', ['id' => $organizer->id, 'group_id' => $new->id]);

        $this->actingAs($organizer)
            ->get(route('groups.show'))
            ->assertOk()
            ->assertSee('Barrio Norte');
    }

    public function test_players_cannot_change_the_group(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player)->patch(route('groups.update'), ['name' => 'Mío'])->assertForbidden();
        $this->actingAs($player)->post(route('groups.store'), ['name' => 'Mío'])->assertForbidden();
        $this->actingAs($player)->post(route('groups.code'))->assertForbidden();
    }

    public function test_the_group_name_leads_the_shared_match_message(): void
    {
        $other = $this->otherGroup();
        $organizer = User::factory()->organizer()->inGroup($other)->create();
        $match = Partido::factory()->inGroup($other->id)->create(['title' => 'Clásico']);

        $this->actingAs($organizer)
            ->patch(route('entries.update', $match), ['role' => 'going'])
            ->assertSessionHas('attendance.message', fn (string $message) => str_contains($message, $other->name)
                && str_contains($message, 'Clásico'));
    }

    public function test_evaluations_of_another_group_do_not_weigh_on_the_profile(): void
    {
        $mine = User::factory()->create();
        $mine->player()->create([
            'group_id' => $mine->group_id,
            'speed' => 4, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'goalkeeping' => 5,
        ]);

        $other = $this->otherGroup();
        $otherOrganizer = User::factory()->organizer()->inGroup($other)->create();

        $otherOrganizer->evaluationsGiven()->create([
            'rated_user_id' => $mine->id,
            'group_id' => $other->id,
            'speed' => 10, 'skill' => 10, 'passing' => 10, 'shooting' => 10, 'defense' => 10, 'goalkeeping' => 10,
        ]);

        $this->assertSame(4.0, app(Scorer::class)->forGroup($mine->group_id)->attributesForUser($mine)['speed']);
    }

    public function test_creating_a_group_moves_the_profile_with_the_organizer(): void
    {
        $organizer = User::factory()->organizer()->create();
        $organizer->player()->create([
            'group_id' => $organizer->group_id,
            'speed' => 7, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'goalkeeping' => 5,
        ]);

        $this->actingAs($organizer)->post(route('groups.store'), ['name' => 'Barrio Norte']);

        $organizer->refresh();

        $this->assertDatabaseHas('players', [
            'user_id' => $organizer->id,
            'group_id' => $organizer->group_id,
        ]);
    }

    public function test_the_same_player_can_be_evaluated_again_after_moving_groups(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();

        $attributes = [
            'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'goalkeeping' => 5,
        ];

        $this->actingAs($organizer)
            ->patch(route('evaluation.update', $player), $attributes)
            ->assertRedirect();

        $other = $this->otherGroup();
        $organizer->moveToGroup($other);
        $player->moveToGroup($other);

        $this->actingAs($organizer)
            ->patch(route('evaluation.update', $player), $attributes)
            ->assertRedirect();

        $this->assertSame(2, PlayerEvaluation::where('rated_user_id', $player->id)->count());
    }
}
