<?php

namespace Tests\Feature;

use App\Models\Partido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MatchFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_match_show_page_renders(): void
    {
        $organizer = User::factory()->organizer()->create();
        $players = User::factory(3)->create();
        $match = Partido::factory()->create(['created_by' => $organizer->id]);

        foreach ($players as $p) {
            $match->entries()->create(['user_id' => $p->id, 'role' => 'going']);
        }

        $this->actingAs($organizer)
            ->get(route('matches.show', $match))
            ->assertOk();
    }

    public function test_organizer_can_create_match(): void
    {
        $organizer = User::factory()->organizer()->create();

        $response = $this->actingAs($organizer)->post('/matches', [
            'title' => 'Sábado',
            'played_at' => now()->addDays(2)->format('Y-m-d H:i'),
            'size' => 5,
        ]);

        $this->assertDatabaseHas('matches', ['title' => 'Sábado']);
        $response->assertRedirect(route('matches.show', Partido::first()));
    }

    public function test_organizer_can_edit_match(): void
    {
        $organizer = User::factory()->organizer()->create();
        $match = Partido::factory()->create(['created_by' => $organizer->id]);

        $this->actingAs($organizer)
            ->get(route('matches.edit', $match))
            ->assertOk()
            ->assertSee('Editar partido')
            ->assertSee($match->title);

        $this->actingAs($organizer)
            ->patch(route('matches.update', $match), [
                'title' => 'Lunes actualizado',
                'played_at' => now()->addDays(3)->format('Y-m-d H:i'),
                'venue' => 'Otra cancha',
                'size' => 4,
            ])
            ->assertRedirect(route('matches.show', $match));

        $this->assertDatabaseHas('matches', [
            'id' => $match->id,
            'title' => 'Lunes actualizado',
            'venue' => 'Otra cancha',
            'size' => 4,
        ]);
    }

    public function test_regular_player_cannot_edit_cancel_or_finish_match(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $match = Partido::factory()->create(['created_by' => $organizer->id]);

        $this->actingAs($player)
            ->patch(route('matches.update', $match), [
                'title' => 'x', 'played_at' => now()->addDay()->format('Y-m-d H:i'), 'size' => 5,
            ])
            ->assertForbidden();

        $this->actingAs($player)
            ->patch(route('matches.cancel', $match))
            ->assertForbidden();
    }

    public function test_organizer_can_cancel_and_reactivate_match(): void
    {
        $organizer = User::factory()->organizer()->create();
        $match = Partido::factory()->create(['created_by' => $organizer->id]);

        $this->actingAs($organizer)
            ->patch(route('matches.cancel', $match))
            ->assertRedirect();

        $this->assertSame(Partido::STATUS_CANCELLED, $match->refresh()->status);

        $this->actingAs($organizer)
            ->patch(route('matches.reactivate', $match))
            ->assertRedirect();

        $this->assertSame(Partido::STATUS_OPEN, $match->refresh()->status);
    }

    public function test_finished_match_cannot_be_cancelled_or_edited(): void
    {
        $organizer = User::factory()->organizer()->create();
        $match = Partido::factory()->create(['created_by' => $organizer->id, 'status' => Partido::STATUS_FINISHED]);

        $this->actingAs($organizer)
            ->patch(route('matches.cancel', $match))
            ->assertForbidden();

        $this->actingAs($organizer)
            ->patch(route('matches.update', $match), [
                'title' => 'x', 'played_at' => now()->addDay()->format('Y-m-d H:i'), 'size' => 5,
            ])
            ->assertForbidden();
    }

    public function test_cancelled_match_renders_with_badge_in_index(): void
    {
        $organizer = User::factory()->organizer()->create();
        Partido::factory()->create(['created_by' => $organizer->id, 'status' => Partido::STATUS_CANCELLED]);

        $this->actingAs($organizer)
            ->get(route('matches.index'))
            ->assertOk()
            ->assertSee('Cancelado');
    }

    public function test_create_match_rejects_size_outside_4_5_6(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)
            ->post('/matches', [
                'title' => 'x',
                'played_at' => now()->addDays(2)->format('Y-m-d H:i'),
                'size' => 3,
            ])
            ->assertSessionHasErrors('size');

        $this->assertDatabaseMissing('matches', ['title' => 'x']);
    }

    public function test_regular_player_cannot_create_match(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player)
            ->post('/matches', ['title' => 'x', 'played_at' => now()->addDay()->format('Y-m-d H:i'), 'size' => 5])
            ->assertForbidden();
    }

    public function test_field_value_is_saved_and_shows_cost_per_person(): void
    {
        $organizer = User::factory()->organizer()->create();
        $match = Partido::factory()->create(['created_by' => $organizer->id, 'field_value' => 10000]);

        // 5v5 → 10 jugadores → 10000 / 10 = 1000
        $this->actingAs($organizer)
            ->get(route('matches.show', $match))
            ->assertOk()
            ->assertSee('Valor:')
            ->assertSee('$1.000 por persona');

        // Cambiar a 4v4 con otro valor de cancha recalcula al instante.
        $this->actingAs($organizer)
            ->patch(route('matches.update', $match), [
                'title' => $match->title,
                'played_at' => now()->addDays(2)->format('Y-m-d H:i'),
                'venue' => $match->venue,
                'field_value' => 12000,
                'size' => 4,
            ])
            ->assertRedirect();

        $match->refresh();
        $this->assertSame(12000, $match->field_value);
        $this->assertSame(4, $match->size);

        // 4v4 → 8 jugadores → 12000 / 8 = 1500
        $this->actingAs($organizer)
            ->get(route('matches.show', $match))
            ->assertSee('$1.500 por persona');
    }

    public function test_players_sign_up_and_teams_are_balanced(): void
    {
        $organizer = User::factory()->organizer()->create();
        $players = User::factory(10)->create();
        $match = Partido::factory()->create(['created_by' => $organizer->id]);

        foreach ($players as $player) {
            $this->actingAs($player)
                ->patch(route('entries.update', $match), ['role' => 'going'])
                ->assertRedirect();
        }

        $match->update(['status' => Partido::STATUS_LOCKED]);

        $this->actingAs($organizer)
            ->post(route('teams.generate', $match), ['size' => 5])
            ->assertRedirect();

        $this->assertSame(10, $match->teams()->count());
        $this->assertSame(5, $match->teams()->where('team', 'A')->count());
        $this->assertSame(5, $match->teams()->where('team', 'B')->count());
    }

    public function test_teams_cannot_be_generated_while_list_is_open(): void
    {
        $organizer = User::factory()->organizer()->create();
        $match = Partido::factory()->create(['created_by' => $organizer->id]);

        $this->actingAs($organizer)
            ->post(route('teams.generate', $match), ['size' => 5])
            ->assertForbidden();

        $this->assertSame(0, $match->teams()->count());
    }

    public function test_result_and_mvp_can_be_recorded(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $match = Partido::factory()->create(['created_by' => $organizer->id, 'played_at' => now()->subDay(), 'status' => Partido::STATUS_FINISHED]);

        $this->actingAs($organizer)
            ->post(route('result.store', $match), [
                'winner' => 'A',
                'diff' => 2,
                'mvp' => 'user:' . $player->id,
            ])
            ->assertRedirect();

        $match->refresh();
        $this->assertSame('finished', $match->status);
        $this->assertSame('A', $match->result->winner);
        $this->assertSame(2, $match->result->diff);
        $this->assertSame('Ganó Equipo A por 2', $match->result->summary);
        $this->assertDatabaseHas('match_results', ['mvp_user_id' => $player->id]);
    }

    public function test_tie_result_can_be_recorded(): void
    {
        $organizer = User::factory()->organizer()->create();
        $match = Partido::factory()->create(['created_by' => $organizer->id, 'played_at' => now()->subDay(), 'status' => Partido::STATUS_FINISHED]);

        $this->actingAs($organizer)
            ->post(route('result.store', $match), [
                'winner' => 'tie',
            ])
            ->assertRedirect();

        $match->refresh();
        $this->assertSame('finished', $match->status);
        $this->assertNull($match->result->winner);
        $this->assertSame(0, $match->result->diff);
        $this->assertSame('Empate', $match->result->summary);
    }

    public function test_open_match_auto_finishes_after_start_time(): void
    {
        $organizer = User::factory()->organizer()->create();
        $match = Partido::factory()->create([
            'created_by' => $organizer->id,
            'played_at' => now()->subDay(),
            'status' => Partido::STATUS_LOCKED,
        ]);

        $this->actingAs($organizer)
            ->get(route('matches.show', $match))
            ->assertOk()
            ->assertSee('Finalizado');

        $this->assertSame(Partido::STATUS_FINISHED, $match->refresh()->status);
    }

    public function test_result_cannot_be_recorded_before_start_time(): void
    {
        $organizer = User::factory()->organizer()->create();
        $match = Partido::factory()->create(['created_by' => $organizer->id, 'played_at' => now()->addDay()]);

        $this->actingAs($organizer)
            ->post(route('result.store', $match), ['winner' => 'A'])
            ->assertForbidden();

        $this->assertDatabaseMissing('match_results', ['match_id' => $match->id]);
        $this->assertNotSame(Partido::STATUS_FINISHED, $match->refresh()->status);
    }

    public function test_result_cannot_be_recorded_when_match_is_not_finished(): void
    {
        $organizer = User::factory()->organizer()->create();
        $match = Partido::factory()->create(['created_by' => $organizer->id, 'played_at' => now()->subDay()]);

        $this->actingAs($organizer)
            ->post(route('result.store', $match), ['winner' => 'A'])
            ->assertForbidden();

        $this->assertDatabaseMissing('match_results', ['match_id' => $match->id]);
    }

    public function test_player_cannot_rate_themselves(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $match = Partido::factory()->create(['created_by' => $me->id]);
        $match->entries()->create(['user_id' => $me->id, 'role' => 'going']);
        $match->entries()->create(['user_id' => $other->id, 'role' => 'going']);

        $this->actingAs($me)
            ->post(route('ratings.store', $match), [
                'rated' => 'user:' . $me->id,
                'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'overall' => 5, 'goalkeeping' => 5,
            ])
            ->assertStatus(422);
    }
}
