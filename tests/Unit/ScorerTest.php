<?php

namespace Tests\Unit;

use App\Models\Guest;
use App\Models\Partido;
use App\Models\Player;
use App\Models\PlayerEvaluation;
use App\Models\Rating;
use App\Models\User;
use App\Services\AlgorithmSettings;
use App\Services\Scorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ScorerTest extends TestCase
{
    use RefreshDatabase;

    private Scorer $scorer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scorer = app(Scorer::class);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function player(array $attributes = []): User
    {
        $user = User::factory()->create();

        Player::factory()->forUser($user)->create(array_merge([
            'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5,
            'defense' => 5, 'goalkeeping' => 5,
        ], $attributes));

        return $user;
    }

    /**
     * @param  array<string, int>  $attributes
     */
    private function evaluation(User $organizer, User $player, array $attributes): PlayerEvaluation
    {
        return PlayerEvaluation::create(array_merge([
            'organizer_user_id' => $organizer->id,
            'rated_user_id' => $player->id,
            'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5,
            'defense' => 5, 'goalkeeping' => 5,
        ], $attributes));
    }

    public function test_attributes_use_the_self_assessment_when_no_organizer_evaluated(): void
    {
        $user = $this->player(['speed' => 7]);

        $this->assertSame(7.0, $this->scorer->attributesForUser($user)['speed']);
    }

    public function test_attributes_blend_the_self_assessment_with_the_organizer_average(): void
    {
        config(['balance.self_weight' => 0.5]);

        $user = $this->player(['speed' => 4]);
        $this->evaluation(User::factory()->organizer()->create(), $user, ['speed' => 8]);

        // 0.5 * 4 + 0.5 * 8 = 6
        $this->assertSame(6.0, $this->scorer->attributesForUser($user)['speed']);
    }

    public function test_average_of_several_organizers_is_used(): void
    {
        config(['balance.self_weight' => 0.5]);

        $user = $this->player(['speed' => 4]);
        $this->evaluation(User::factory()->organizer()->create(), $user, ['speed' => 8]);
        $this->evaluation(User::factory()->organizer()->create(), $user, ['speed' => 10]);

        // organizers average 9, then 0.5 * 4 + 0.5 * 9 = 6.5
        $this->assertSame(6.5, $this->scorer->attributesForUser($user)['speed']);
    }

    public function test_the_self_weight_can_hand_everything_to_the_organizers(): void
    {
        config(['balance.self_weight' => 0.0]);

        $user = $this->player(['speed' => 4]);
        $this->evaluation(User::factory()->organizer()->create(), $user, ['speed' => 8]);

        $this->assertSame(8.0, $this->scorer->attributesForUser($user)['speed']);
    }

    public function test_recent_form_scales_the_profile_against_the_group_average(): void
    {
        config(['balance.self_weight' => 1.0]);

        $user = $this->player(['speed' => 6]);
        $other = $this->player(['speed' => 5]);
        $rater = User::factory()->create();

        $match = Partido::factory()->create([
            'status' => Partido::STATUS_FINISHED,
            'played_at' => now()->subDay(),
        ]);

        $match->entries()->create(['user_id' => $user->id, 'role' => 'going']);
        $match->entries()->create(['user_id' => $other->id, 'role' => 'going']);

        Rating::create(['rater_user_id' => $rater->id, 'rated_user_id' => $user->id, 'match_id' => $match->id, 'overall' => 8]);
        Rating::create(['rater_user_id' => $rater->id, 'rated_user_id' => $other->id, 'match_id' => $match->id, 'overall' => 4]);

        $form = $this->scorer->formForUser($user);

        $this->assertSame(8.0, $form['general']);
        $this->assertSame(6.0, $form['group']);
        $this->assertEqualsWithDelta(8.0, $this->scorer->attributesForUser($user)['speed'], 0.01);
    }

    public function test_form_weighting_can_be_disabled(): void
    {
        config(['balance.self_weight' => 1.0]);

        $user = $this->player(['speed' => 6]);
        $other = $this->player(['speed' => 5]);
        $rater = User::factory()->create();

        $match = Partido::factory()->create([
            'status' => Partido::STATUS_FINISHED,
            'played_at' => now()->subDay(),
        ]);

        $match->entries()->create(['user_id' => $user->id, 'role' => 'going']);
        $match->entries()->create(['user_id' => $other->id, 'role' => 'going']);

        Rating::create(['rater_user_id' => $rater->id, 'rated_user_id' => $user->id, 'match_id' => $match->id, 'overall' => 8]);
        Rating::create(['rater_user_id' => $rater->id, 'rated_user_id' => $other->id, 'match_id' => $match->id, 'overall' => 4]);

        app(AlgorithmSettings::class)->update(['self_weight' => 1.0, 'weight_by_form' => false]);

        $this->assertSame(6.0, $this->scorer->attributesForUser($user)['speed']);
    }

    public function test_matches_before_the_last_three_are_ignored(): void
    {
        config(['balance.self_weight' => 1.0]);

        $user = $this->player(['speed' => 5]);
        $rater = User::factory()->create();

        // Three old matches rate the player high; a fourth, newest, rates low.
        foreach ([10, 10, 10, 2] as $index => $overall) {
            $match = Partido::factory()->create([
                'status' => Partido::STATUS_FINISHED,
                'played_at' => now()->subDays(10 - $index),
            ]);

            $match->entries()->create(['user_id' => $user->id, 'role' => 'going']);
            Rating::create(['rater_user_id' => $rater->id, 'rated_user_id' => $user->id, 'match_id' => $match->id, 'overall' => $overall]);
        }

        $form = $this->scorer->formForUser($user);

        // 2 + 10 + 10 (the three newest) average 7.33; the group average is the
        // same, so the multiplier is 1.
        $this->assertSame(7.33, $form['general']);
        $this->assertSame(1.0, $form['multiplier']);
    }

    public function test_for_guest_uses_overall(): void
    {
        $this->assertSame(8.0, $this->scorer->forGuest(new Guest(['overall' => 8])));
    }

    public function test_for_guest_defaults_to_five(): void
    {
        $this->assertSame(5.0, $this->scorer->forGuest(new Guest));
    }

    public function test_power_weights_speed_above_skill(): void
    {
        $fast = ['speed' => 10, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5];
        $skilled = ['speed' => 5, 'skill' => 10, 'passing' => 5, 'shooting' => 5, 'defense' => 5];

        $this->assertGreaterThan($this->scorer->power($skilled), $this->scorer->power($fast));
    }

    public function test_attributes_for_guest_reads_every_characteristic(): void
    {
        $attributes = $this->scorer->attributesForGuest(new Guest(['speed' => 9, 'skill' => 3]));

        $this->assertSame(9.0, $attributes['speed']);
        $this->assertSame(3.0, $attributes['skill']);
        $this->assertSame(5.0, $attributes['passing']);
        $this->assertSame(5.0, $attributes['defense']);
    }
}
