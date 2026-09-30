<?php

namespace Tests\Feature;

use App\Models\PlayerEvaluation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlayerEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_evaluate_a_player(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();

        $this->actingAs($organizer)
            ->get(route('evaluation.edit', $player))
            ->assertOk()
            ->assertSee('data-live="1"', false)
            ->assertSee('Evaluar a '.$player->name);

        $this->actingAs($organizer)
            ->patch(route('evaluation.update', $player), [
                'speed' => 7, 'skill' => 6, 'passing' => 5,
                'shooting' => 8, 'defense' => 4, 'goalkeeping' => 3,
            ])
            ->assertRedirect(route('stats.show', $player));

        $this->assertDatabaseHas('player_evaluations', [
            'organizer_user_id' => $organizer->id,
            'rated_user_id' => $player->id,
            'speed' => 7,
            'goalkeeping' => 3,
        ]);
    }

    public function test_evaluation_is_editable_and_never_accumulates(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $payload = ['speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'goalkeeping' => 5];

        $this->actingAs($organizer)->patch(route('evaluation.update', $player), $payload);
        $this->actingAs($organizer)->patch(route('evaluation.update', $player), array_merge($payload, ['speed' => 9]));

        $this->assertSame(1, PlayerEvaluation::where('rated_user_id', $player->id)->count());
        $this->assertDatabaseHas('player_evaluations', ['rated_user_id' => $player->id, 'speed' => 9]);
    }

    public function test_players_cannot_evaluate_and_organizers_cannot_evaluate_themselves(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();

        $this->actingAs($player)->get(route('evaluation.edit', $organizer))->assertForbidden();
        $this->actingAs($organizer)->get(route('evaluation.edit', $organizer))->assertForbidden();
    }

    public function test_stats_show_tooltips_with_self_and_organizer_values(): void
    {
        $organizer = User::factory()->organizer()->create();
        $player = User::factory()->create();
        $player->player()->create([
            'speed' => 8, 'skill' => 6, 'passing' => 5, 'shooting' => 7, 'defense' => 4, 'goalkeeping' => 9,
            'self_eval_completed_at' => now(),
        ]);
        PlayerEvaluation::create([
            'organizer_user_id' => $organizer->id,
            'rated_user_id' => $player->id,
            'speed' => 6, 'skill' => 7, 'passing' => 4, 'shooting' => 5, 'defense' => 6, 'goalkeeping' => 8,
        ]);

        $this->actingAs($organizer)
            ->get(route('stats.show', $player))
            ->assertOk()
            ->assertSee('tip-self', false)
            ->assertSee('tip-org', false)
            ->assertSee('¿Le gusta ir al arco?')
            ->assertDontSee('Sin autoevaluación registrada');
    }
}
