<?php

namespace Tests\Unit;

use App\Models\Partido;
use App\Models\User;
use App\Services\MessagingService;
use App\Support\TeamKit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MessagingServiceTest extends TestCase
{
    use RefreshDatabase;

    private MessagingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MessagingService(new TeamKit);
    }

    public function test_anotados_lists_every_slot_of_a_five_a_side_match(): void
    {
        $alfa = User::factory()->create(['name' => 'Alfa']);
        $beta = User::factory()->create(['name' => 'Beta']);
        $match = Partido::factory()->create(['size' => 5]);
        $match->entries()->create(['user_id' => $alfa->id, 'role' => 'going']);
        $match->entries()->create(['user_id' => $beta->id, 'role' => 'going']);

        $message = $this->service->message(
            $match,
            collect(),
            collect(),
            $match->entries()->where('role', 'going')->get()->values(),
            collect(),
        );

        $this->assertStringContainsString('*Anotados* (2/10):', $message);
        $this->assertStringContainsString('   01. Alfa', $message);
        $this->assertStringContainsString('   02. Beta', $message);
        $this->assertStringContainsString('   03.', $message);
        $this->assertStringContainsString('   10.', $message);
        $this->assertStringNotContainsString('   11.', $message);

        // With the list open the invite to sign up is still the point.
        $this->assertStringContainsString('¿Te sumás al próximo? Anotate acá: '.url('/matches/'.$match->id), $message);
        $this->assertStringNotContainsString(TeamKit::LIGHT, $message);
        $this->assertStringNotContainsString(TeamKit::DARK, $message);
    }

    public function test_anotados_pads_numbers_and_stops_at_twelve_for_six_a_side(): void
    {
        $solo = User::factory()->create(['name' => 'Solo']);
        $match = Partido::factory()->create(['size' => 6]);
        $match->entries()->create(['user_id' => $solo->id, 'role' => 'going']);

        $message = $this->service->message(
            $match,
            collect(),
            collect(),
            $match->entries()->where('role', 'going')->get()->values(),
            collect(),
        );

        $this->assertStringContainsString('*Anotados* (1/12):', $message);
        $this->assertStringContainsString('   01. Solo', $message);
        $this->assertStringContainsString('   12.', $message);
        $this->assertStringNotContainsString('   13.', $message);
    }

    public function test_teams_are_listed_when_generated(): void
    {
        $alfa = User::factory()->create(['name' => 'Alfa']);
        $beta = User::factory()->create(['name' => 'Beta']);
        $match = Partido::factory()->create(['size' => 5]);
        $teamA = $match->teams()->create(['user_id' => $alfa->id, 'team' => 'A']);
        $teamB = $match->teams()->create(['user_id' => $beta->id, 'team' => 'B']);

        $message = $this->service->message(
            $match,
            collect([$teamA->load('user')]),
            collect([$teamB->load('user')]),
            $match->entries()->where('role', 'going')->get()->values(),
            collect(),
        );

        $this->assertStringContainsString('*Equipo A* (1):', $message);
        $this->assertStringContainsString('   1. Alfa', $message);
        $this->assertStringContainsString('*Equipo B* (1):', $message);
        $this->assertStringContainsString('   1. Beta', $message);
        $this->assertStringNotContainsString('Anotados', $message);

        // With the teams out, the invite to join is gone; the match link stays.
        $this->assertStringNotContainsString('¿Te sumás al próximo?', $message);
        $this->assertStringContainsString(url('/matches/'.$match->id), $message);
    }

    public function test_each_team_header_carries_opposite_shirt_colours(): void
    {
        $alfa = User::factory()->create(['name' => 'Alfa']);
        $beta = User::factory()->create(['name' => 'Beta']);
        $match = Partido::factory()->create(['size' => 5]);
        $teamA = $match->teams()->create(['user_id' => $alfa->id, 'team' => 'A']);
        $teamB = $match->teams()->create(['user_id' => $beta->id, 'team' => 'B']);

        $message = $this->service->message(
            $match,
            collect([$teamA->load('user')]),
            collect([$teamB->load('user')]),
            $match->entries()->where('role', 'going')->get()->values(),
            collect(),
        );

        $headers = array_values(array_filter(
            explode("\n", $message),
            fn (string $line): bool => str_starts_with($line, '*Equipo '),
        ));

        $this->assertCount(2, $headers);
        $this->assertStringNotContainsString('camiseta', $message);

        // The header carries the icon and nothing else.
        $this->assertMatchesRegularExpression('/^\*Equipo A\* \(1\): (⚪|⚫)$/u', $headers[0]);
        $this->assertMatchesRegularExpression('/^\*Equipo B\* \(1\): (⚪|⚫)$/u', $headers[1]);

        // One side wears the light shirt, the other the dark one.
        $this->assertNotSame(
            str_contains($headers[0], TeamKit::LIGHT),
            str_contains($headers[1], TeamKit::LIGHT),
        );
    }

    public function test_the_shirt_draw_is_stable_for_the_same_match_and_varies_between_matches(): void
    {
        $alfa = User::factory()->create(['name' => 'Alfa']);
        $beta = User::factory()->create(['name' => 'Beta']);
        $sides = [];

        foreach (range(1, 10) as $_) {
            $match = Partido::factory()->create(['size' => 5]);
            $teamA = $match->teams()->create(['user_id' => $alfa->id, 'team' => 'A']);
            $teamB = $match->teams()->create(['user_id' => $beta->id, 'team' => 'B']);

            $message = fn (): string => $this->service->message(
                $match,
                collect([$teamA->load('user')]),
                collect([$teamB->load('user')]),
                collect(),
                collect(),
            );

            $first = $message();

            // Reading the same match again (or regenerating the lineup) never
            // swaps the shirts.
            $this->assertSame($first, $message());

            $sides[] = str_contains($first, '*Equipo A* (1): ⚪') ? 'A' : 'B';
        }

        $this->assertContains('A', $sides);
        $this->assertContains('B', $sides);
    }

    public function test_a_closed_list_drops_the_invite_but_keeps_the_match_link(): void
    {
        $solo = User::factory()->create(['name' => 'Solo']);
        $match = Partido::factory()->create(['size' => 5, 'status' => 'locked']);
        $match->entries()->create(['user_id' => $solo->id, 'role' => 'going']);

        $message = $this->service->message(
            $match,
            collect(),
            collect(),
            $match->entries()->where('role', 'going')->get()->values(),
            collect(),
        );

        // The lineup is not generated yet, but the list is closed: nobody can
        // sign up, so the invite would be a dead end.
        $this->assertStringContainsString('*Anotados* (1/10):', $message);
        $this->assertStringNotContainsString('¿Te sumás al próximo?', $message);
        $this->assertStringContainsString(url('/matches/'.$match->id), $message);
    }
}
