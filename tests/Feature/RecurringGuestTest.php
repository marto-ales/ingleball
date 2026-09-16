<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Partido;
use App\Models\User;
use App\Services\RecurringMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class RecurringGuestTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_recurring_match_marks_template(): void
    {
        $organizer = User::factory()->organizer()->create();

        $this->actingAs($organizer)->post(route('matches.store'), [
            'title' => 'Fútbol de los sábados',
            'played_at' => now()->addDays(6)->format('Y-m-d\TH:i'),
            'size' => 5,
            'recurring' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('matches', ['title' => 'Fútbol de los sábados', 'recurring' => true]);
    }

    public function test_ensure_upcoming_creates_the_next_weekly_occurrence_only_once(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        $organizer = User::factory()->organizer()->create();
        Partido::factory()->createdBy($organizer)->create([
            'title' => 'Fútbol de los sábados',
            'played_at' => '2026-08-26 12:00:00',
            'recurring' => true,
        ]);

        $service = app(RecurringMatchService::class);
        $this->assertSame(1, $service->ensureUpcoming());
        $this->assertDatabaseHas('matches', [
            'title' => 'Fútbol de los sábados',
            'played_at' => '2026-09-23 12:00:00',
            'recurring' => false,
        ]);

        $this->assertSame(0, $service->ensureUpcoming());
        $this->assertSame(2, Partido::count());

        Carbon::setTestNow();
    }

    public function test_toggle_recurring_route_works_for_organizer(): void
    {
        $organizer = User::factory()->organizer()->create();
        $match = Partido::factory()->createdBy($organizer)->create(['played_at' => now()->addDay()]);

        $this->actingAs($organizer)
            ->post(route('matches.recurring', $match))
            ->assertRedirect();

        $this->assertTrue($match->refresh()->recurring);

        $this->actingAs($organizer)
            ->post(route('matches.recurring', $match))
            ->assertRedirect();

        $this->assertFalse($match->refresh()->recurring);
    }

    public function test_adding_same_guest_twice_in_one_match_does_not_duplicate(): void
    {
        $organizer = User::factory()->organizer()->create();
        $match = Partido::factory()->createdBy($organizer)->create(['played_at' => now()->addDay()]);

        $this->actingAs($organizer)->post(route('guests.store', $match), [
            'name' => 'Cheto', 'phone' => '+5491155550099',
        ])->assertRedirect();

        $this->actingAs($organizer)->post(route('guests.store', $match), [
            'name' => 'Cheto', 'phone' => '+5491155550099',
        ])->assertRedirect();

        $this->assertSame(1, Guest::count());
        $this->assertSame(1, $match->entries()->where('role', 'going')->count());
    }

    public function test_guest_is_global_and_reused_across_matches(): void
    {
        $organizer = User::factory()->organizer()->create();
        $matchA = Partido::factory()->createdBy($organizer)->create(['played_at' => now()->addDay()]);
        $matchB = Partido::factory()->createdBy($organizer)->create(['played_at' => now()->addDays(2)]);

        $this->actingAs($organizer)->post(route('guests.store', $matchA), [
            'name' => 'Cheto', 'phone' => '+5491155550099', 'overall' => 8,
        ]);
        $this->actingAs($organizer)->post(route('guests.store', $matchB), [
            'name' => 'Cheto', 'phone' => '+5491155550099', 'overall' => 3,
        ]);

        $this->assertSame(1, Guest::count());
        $guest = Guest::first();
        $this->assertSame(3, $guest->overall);
        $this->assertSame(2, $guest->entries()->count());
    }

    public function test_removing_guest_keeps_their_history_when_played_other_matches(): void
    {
        $organizer = User::factory()->organizer()->create();
        $matchA = Partido::factory()->createdBy($organizer)->create(['played_at' => now()->addDay()]);
        $matchB = Partido::factory()->createdBy($organizer)->create(['played_at' => now()->addDays(2)]);

        $this->actingAs($organizer)->post(route('guests.store', $matchA), ['name' => 'Cheto', 'phone' => '+5491155550099']);
        $this->actingAs($organizer)->post(route('guests.store', $matchB), ['name' => 'Cheto', 'phone' => '+5491155550099']);

        $guest = Guest::first();

        $this->actingAs($organizer)
            ->delete(route('guests.destroy', [$matchA, $guest]))
            ->assertRedirect();

        $this->assertDatabaseHas('guests', ['id' => $guest->id]);
        $this->assertSame(1, $guest->entries()->count());
    }
}