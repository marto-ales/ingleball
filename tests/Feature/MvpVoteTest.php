<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MvpVoteTest extends TestCase
{
    use RefreshDatabase;

    private function finishedMatch(User $organizer, array $overrides = []): Partido
    {
        return Partido::factory()->create(array_merge([
            'created_by' => $organizer->id,
            'played_at' => now()->subDay(),
            'status' => Partido::STATUS_FINISHED,
        ], $overrides));
    }

    /**
     * @param  array<int, User>  $players
     */
    private function signUp(Partido $match, array $players, string $role = 'going'): void
    {
        foreach ($players as $player) {
            $match->entries()->create(['user_id' => $player->id, 'role' => $role]);
        }
    }

    public function test_the_most_voted_attendee_becomes_mvp(): void
    {
        $organizer = User::factory()->organizer()->create();
        [$a, $b, $c] = User::factory(3)->create();
        $match = $this->finishedMatch($organizer);
        $this->signUp($match, [$organizer, $a, $b, $c]);
        $match->result()->create(['winner' => 'A', 'diff' => 1]);

        $this->actingAs($a)->post(route('mvp.store', $match), ['mvp' => 'user:'.$b->id])->assertRedirect();
        $this->actingAs($c)->post(route('mvp.store', $match), ['mvp' => 'user:'.$b->id])->assertRedirect();
        $this->actingAs($organizer)->post(route('mvp.store', $match), ['mvp' => 'user:'.$a->id])->assertRedirect();

        $match->refresh();
        $this->assertSame($b->id, $match->result->mvp_user_id);
        $this->assertNull($match->result->mvp_guest_id);
        $this->assertSame(3, $match->mvpVotes()->count());
    }

    public function test_a_guest_can_be_voted_mvp(): void
    {
        $organizer = User::factory()->organizer()->create();
        $voter = User::factory()->create();
        $guest = Guest::factory()->create();
        $match = $this->finishedMatch($organizer);
        $this->signUp($match, [$organizer, $voter]);
        $match->entries()->create(['guest_id' => $guest->id, 'role' => 'going']);
        $match->result()->create(['winner' => 'B', 'diff' => 2]);

        $this->actingAs($voter)
            ->post(route('mvp.store', $match), ['mvp' => 'guest:'.$guest->id])
            ->assertRedirect();

        $match->refresh();
        $this->assertSame($guest->id, $match->result->mvp_guest_id);
        $this->assertNull($match->result->mvp_user_id);
    }

    public function test_only_attendees_can_vote(): void
    {
        $organizer = User::factory()->organizer()->create();
        $attending = User::factory()->create();
        $outsider = User::factory()->create();
        $match = $this->finishedMatch($organizer);
        $this->signUp($match, [$organizer, $attending]);
        $match->result()->create(['winner' => 'A', 'diff' => 1]);

        $this->actingAs($outsider)
            ->post(route('mvp.store', $match), ['mvp' => 'user:'.$attending->id])
            ->assertForbidden();

        $this->assertSame(0, $match->mvpVotes()->count());
    }

    public function test_substitutes_cannot_vote_or_be_elected(): void
    {
        $organizer = User::factory()->organizer()->create();
        $attending = User::factory()->create();
        $substitute = User::factory()->create();
        $match = $this->finishedMatch($organizer);
        $this->signUp($match, [$organizer, $attending]);
        $this->signUp($match, [$substitute], 'substitute');
        $match->result()->create(['winner' => 'A', 'diff' => 1]);

        $this->actingAs($substitute)
            ->post(route('mvp.store', $match), ['mvp' => 'user:'.$attending->id])
            ->assertForbidden();

        $this->actingAs($attending)
            ->post(route('mvp.store', $match), ['mvp' => 'user:'.$substitute->id])
            ->assertStatus(422);
    }

    public function test_an_attendee_cannot_vote_for_themselves(): void
    {
        $organizer = User::factory()->organizer()->create();
        $attending = User::factory()->create();
        $match = $this->finishedMatch($organizer);
        $this->signUp($match, [$organizer, $attending]);
        $match->result()->create(['winner' => 'A', 'diff' => 1]);

        $this->actingAs($attending)
            ->post(route('mvp.store', $match), ['mvp' => 'user:'.$attending->id])
            ->assertStatus(422);
    }

    public function test_an_attendee_can_change_their_vote(): void
    {
        $organizer = User::factory()->organizer()->create();
        [$voter, $first, $second] = User::factory(3)->create();
        $match = $this->finishedMatch($organizer);
        $this->signUp($match, [$organizer, $voter, $first, $second]);
        $match->result()->create(['winner' => 'A', 'diff' => 1]);

        $this->actingAs($voter)->post(route('mvp.store', $match), ['mvp' => 'user:'.$first->id]);
        $this->actingAs($voter)->post(route('mvp.store', $match), ['mvp' => 'user:'.$second->id])->assertRedirect();

        $this->assertSame(1, $match->mvpVotes()->count());

        $match->refresh();
        $this->assertSame($second->id, $match->result->mvp_user_id);
    }

    public function test_voting_requires_the_result_to_be_recorded(): void
    {
        $organizer = User::factory()->organizer()->create();
        $attending = User::factory()->create();
        $match = $this->finishedMatch($organizer);
        $this->signUp($match, [$organizer, $attending]);

        // The match is over but the organizer has not loaded the result yet.
        $this->assertNull($match->result);

        $this->actingAs($attending)
            ->post(route('mvp.store', $match), ['mvp' => 'user:'.$organizer->id])
            ->assertForbidden();

        $this->assertSame(0, $match->mvpVotes()->count());
    }

    public function test_a_tie_leaves_the_mvp_unset_until_the_organizer_breaks_it(): void
    {
        $organizer = User::factory()->organizer()->create();
        [$a, $b, $c] = User::factory(3)->create();
        $match = $this->finishedMatch($organizer);
        $this->signUp($match, [$organizer, $a, $b, $c]);
        $match->result()->create(['winner' => 'A', 'diff' => 1]);

        // a votes b, c votes a: one vote each.
        $this->actingAs($a)->post(route('mvp.store', $match), ['mvp' => 'user:'.$b->id]);
        $this->actingAs($c)->post(route('mvp.store', $match), ['mvp' => 'user:'.$a->id]);

        $match->refresh();
        $this->assertNull($match->result->mvp_user_id);

        // Only a player tied for first can be picked.
        $this->actingAs($organizer)
            ->post(route('mvp.break-tie', $match), ['mvp' => 'user:'.$c->id])
            ->assertStatus(422);

        $this->actingAs($organizer)
            ->post(route('mvp.break-tie', $match), ['mvp' => 'user:'.$a->id])
            ->assertRedirect();

        $match->refresh();
        $this->assertSame($a->id, $match->result->mvp_user_id);

        // A later vote breaks the tie outright and overrides the organizer.
        $this->actingAs($c)->post(route('mvp.store', $match), ['mvp' => 'user:'.$b->id]);

        $match->refresh();
        $this->assertSame($b->id, $match->result->mvp_user_id);
    }

    public function test_the_organizers_tie_break_survives_a_still_tied_vote(): void
    {
        $organizer = User::factory()->organizer()->create();
        [$a, $b, $c, $d] = User::factory(4)->create();
        $match = $this->finishedMatch($organizer);
        $this->signUp($match, [$organizer, $a, $b, $c, $d]);
        $match->result()->create(['winner' => 'A', 'diff' => 1]);

        $this->actingAs($a)->post(route('mvp.store', $match), ['mvp' => 'user:'.$b->id]);
        $this->actingAs($c)->post(route('mvp.store', $match), ['mvp' => 'user:'.$a->id]);

        $this->actingAs($organizer)
            ->post(route('mvp.break-tie', $match), ['mvp' => 'user:'.$a->id]);

        // A new vote keeps the tie alive, so the organizer's pick stands.
        $this->actingAs($d)->post(route('mvp.store', $match), ['mvp' => 'user:'.$c->id]);

        $match->refresh();
        $this->assertSame($a->id, $match->result->mvp_user_id);
        $this->assertTrue($match->result->mvp_tiebreak);
    }

    public function test_the_organizer_sees_the_tie_break_picker(): void
    {
        $organizer = User::factory()->organizer()->create();
        [$a, $b, $c] = User::factory(3)->create();
        $match = $this->finishedMatch($organizer);
        $this->signUp($match, [$organizer, $a, $b, $c]);
        $match->result()->create(['winner' => 'A', 'diff' => 1]);

        $this->actingAs($a)->post(route('mvp.store', $match), ['mvp' => 'user:'.$b->id]);
        $this->actingAs($c)->post(route('mvp.store', $match), ['mvp' => 'user:'.$a->id]);

        $this->actingAs($organizer)
            ->get(route('matches.show', $match))
            ->assertOk()
            ->assertSee('Empate en la votación de MVP')
            ->assertSee($a->name)
            ->assertSee($b->name);
    }

    public function test_votes_after_the_result_become_the_mvp(): void
    {
        $organizer = User::factory()->organizer()->create();
        [$voter, $candidate] = User::factory(2)->create();
        $match = $this->finishedMatch($organizer);
        $this->signUp($match, [$organizer, $voter, $candidate]);

        $this->actingAs($organizer)
            ->post(route('result.store', $match), ['winner' => 'A', 'diff' => 3])
            ->assertRedirect();

        // Recording the result does not invent an MVP; the vote decides it.
        $this->assertNull($match->result()->first()->mvp_user_id);

        $this->actingAs($voter)
            ->post(route('mvp.store', $match), ['mvp' => 'user:'.$candidate->id])
            ->assertRedirect();

        $match->refresh();
        $this->assertSame($candidate->id, $match->result->mvp_user_id);
    }

    public function test_the_ratings_page_offers_the_mvp_vote_to_attendees(): void
    {
        $organizer = User::factory()->organizer()->create();
        $attending = User::factory()->create();
        $match = $this->finishedMatch($organizer);
        $this->signUp($match, [$organizer, $attending]);
        $match->result()->create(['winner' => 'A', 'diff' => 1]);

        $this->actingAs($attending)
            ->get(route('ratings.create', $match))
            ->assertOk()
            ->assertSee('MVP del partido')
            ->assertSee($organizer->name);
    }
}
