<?php

namespace Tests\Feature;

use App\Models\Partido;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class RatingsDetailCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_ratings_detail_is_grouped_by_rated_player(): void
    {
        $rater = User::factory()->create(['name' => 'Rater Uno']);
        $rated = User::factory()->create(['name' => 'Jugador Calificado']);
        $match = Partido::factory()->create([
            'created_by' => $rater->id,
            'status' => Partido::STATUS_FINISHED,
        ]);

        $match->entries()->create(['user_id' => $rater->id, 'role' => 'going']);
        $match->entries()->create(['user_id' => $rated->id, 'role' => 'going']);

        Rating::create(['rater_user_id' => $rater->id, 'rated_user_id' => $rated->id, 'match_id' => $match->id, 'overall' => 9]);
        Rating::create(['rater_user_id' => $rated->id, 'rated_user_id' => $rater->id, 'match_id' => $match->id, 'overall' => 6]);

        $this->assertSame(0, Artisan::call('ratings:detail'));
        $output = Artisan::output();

        $this->assertStringContainsString('Jugador Calificado: 1 calificación · promedio 9.0', $output);
        $this->assertStringContainsString('Puntaje: 9', $output);
        $this->assertStringContainsString('Calificado por: Rater Uno', $output);
        $this->assertStringContainsString('Total: 2 calificaciones de 2 jugadores calificados', $output);
    }

    public function test_ratings_detail_filters_by_user_and_match(): void
    {
        $organizer = User::factory()->organizer()->create();
        $players = User::factory(2)->create();
        $rated = $players->first();
        $match = Partido::factory()->create([
            'created_by' => $organizer->id,
            'status' => Partido::STATUS_FINISHED,
        ]);

        foreach ($players as $player) {
            $match->entries()->create(['user_id' => $player->id, 'role' => 'going']);
        }

        Rating::create(['rater_user_id' => $players->last()->id, 'rated_user_id' => $rated->id, 'match_id' => $match->id, 'overall' => 8]);

        $this->assertSame(0, Artisan::call('ratings:detail', ['--user' => $rated->name, '--match' => $match->id]));
        $output = Artisan::output();

        $this->assertStringContainsString('Puntaje: 8', $output);
        $this->assertStringContainsString('Total: 1 calificaciones de 1 jugador calificado', $output);
    }
}
