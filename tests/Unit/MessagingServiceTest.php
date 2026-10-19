<?php

namespace Tests\Unit;

use App\Models\Partido;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MessagingServiceTest extends TestCase
{
    use RefreshDatabase;

    private MessagingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new MessagingService;
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
    }
}
