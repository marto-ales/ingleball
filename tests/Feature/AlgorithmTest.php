<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AlgorithmSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AlgorithmTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_can_open_and_update_the_algorithm_screen(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)
            ->get('/algorithm')
            ->assertOk()
            ->assertSee('Orden de peso');

        $this->actingAs($organizer)
            ->patch('/algorithm', [
                'order' => ['skill', 'speed', 'passing', 'shooting', 'defense'],
                'random_tie_break' => '1',
                'self_weight' => 0.5,
            ])
            ->assertRedirect();

        $settings = new AlgorithmSettings;

        $this->assertSame('skill', $settings->order()[0]);
        $this->assertSame('speed', $settings->order()[1]);
        $this->assertTrue($settings->randomTieBreak());
        $this->assertFalse($settings->spreadGoalies());
        $this->assertSame(0.5, $settings->selfWeight());
    }

    public function test_form_span_can_be_tuned_only_while_form_weighting_is_on(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)
            ->patch('/algorithm', [
                'order' => ['speed', 'skill', 'passing', 'shooting', 'defense'],
                'self_weight' => 0.5,
                'weight_by_form' => '1',
                'form_span' => 0.4,
            ])
            ->assertRedirect();

        $settings = new AlgorithmSettings;

        $this->assertTrue($settings->weightByForm());
        $this->assertSame(0.4, $settings->formSpan());

        $this->actingAs($organizer)
            ->patch('/algorithm', [
                'order' => ['speed', 'skill', 'passing', 'shooting', 'defense'],
                'self_weight' => 0.5,
                'weight_by_form' => '0',
            ])
            ->assertRedirect();

        // Disabling the form weighting keeps the tuned span for later.
        $this->assertFalse($settings->weightByForm());
        $this->assertSame(0.4, $settings->formSpan());
    }

    public function test_duplicate_positions_are_rejected(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)
            ->patch('/algorithm', [
                'order' => ['speed', 'speed', 'passing', 'shooting', 'defense'],
                'self_weight' => 0.5,
            ])
            ->assertSessionHasErrors('order');
    }

    public function test_players_cannot_open_the_algorithm_screen(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player)->get('/algorithm')->assertForbidden();
    }
}
