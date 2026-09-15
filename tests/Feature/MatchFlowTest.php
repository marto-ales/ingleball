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
            $match->entries()->create(['user_id' => $p->id, 'role' => 'going', 'list_order' => 1]);
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

    public function test_regular_player_cannot_create_match(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player)
            ->post('/matches', ['title' => 'x', 'played_at' => now()->addDay()->format('Y-m-d H:i'), 'size' => 5])
            ->assertForbidden();
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

        $this->actingAs($organizer)
            ->post(route('teams.generate', $match), ['size' => 5])
            ->assertRedirect();

        $this->assertSame(10, $match->teams()->count());
        $this->assertSame(5, $match->teams()->where('team', 'A')->count());
        $this->assertSame(5, $match->teams()->where('team', 'B')->count());
    }

    public function test_result_and_mvp_can_be_recorded(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $match = Partido::factory()->create(['created_by' => $organizer->id]);

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
        $match = Partido::factory()->create(['created_by' => $organizer->id]);

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
                'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'overall' => 5,
            ])
            ->assertStatus(422);
    }
}
