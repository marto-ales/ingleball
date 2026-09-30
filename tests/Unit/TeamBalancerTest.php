<?php

namespace Tests\Unit;

use App\Services\TeamBalancer;
use Tests\TestCase;

final class TeamBalancerTest extends TestCase
{
    private TeamBalancer $balancer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->balancer = new TeamBalancer;
    }

    /**
     * @param  array<string, int>  $overrides
     * @return array<string, int>
     */
    private function attributes(array $overrides = []): array
    {
        return array_merge([
            'speed' => 5,
            'skill' => 5,
            'passing' => 5,
            'shooting' => 5,
            'defense' => 5,
        ], $overrides);
    }

    public function test_produces_two_teams_of_the_requested_size(): void
    {
        $participants = collect(range(1, 10))->map(fn ($i) => ['id' => $i, 'score' => (float) $i])->all();

        $result = $this->balancer->balance($participants, 5);

        $this->assertCount(5, $result['teamA']);
        $this->assertCount(5, $result['teamB']);
        $this->assertSame(10, count($result['teamA']) + count($result['teamB']));
    }

    public function test_teams_are_balanced_by_total_score(): void
    {
        $participants = collect([10, 9, 8, 7, 6, 5, 4, 3, 2, 1])->map(fn ($s) => ['score' => (float) $s])->all();

        $result = $this->balancer->balance($participants, 5);

        $sumA = collect($result['teamA'])->sum('score');
        $sumB = collect($result['teamB'])->sum('score');

        $this->assertLessThanOrEqual(2.0, abs($sumA - $sumB));
    }

    public function test_handles_smaller_teams(): void
    {
        $participants = collect([8, 7, 6, 5])->map(fn ($s) => ['score' => (float) $s])->all();

        $result = $this->balancer->balance($participants, 2);

        $this->assertCount(2, $result['teamA']);
        $this->assertCount(2, $result['teamB']);
    }

    public function test_keeps_every_player_even_with_equal_scores(): void
    {
        $participants = collect([9, 9, 8, 8])->map(fn ($s) => ['score' => (float) $s])->all();

        $result = $this->balancer->balance($participants, 2);

        $this->assertSame(4, count($result['teamA']) + count($result['teamB']));
    }

    public function test_spreads_two_goalies_one_per_team(): void
    {
        $goalies = collect([9.0, 8.0])->map(fn ($s) => ['score' => $s, 'likes_goalie' => true])->all();
        $others = collect(range(1, 8))->map(fn ($s) => ['score' => (float) $s])->all();

        $result = $this->balancer->balance(array_merge($goalies, $others), 5);

        $gkA = collect($result['teamA'])->where('likes_goalie', true)->count();
        $gkB = collect($result['teamB'])->where('likes_goalie', true)->count();

        $this->assertSame(1, $gkA);
        $this->assertSame(1, $gkB);
    }

    public function test_goalies_are_distributed_when_many_are_available(): void
    {
        $goalies = collect([9.0, 8.0, 7.0, 6.0])->map(fn ($s) => ['score' => $s, 'likes_goalie' => true])->all();
        $others = collect([5.0, 4.0, 3.0, 2.0, 1.0, 1.0])->map(fn ($s) => ['score' => (float) $s])->all();

        $result = $this->balancer->balance(array_merge($goalies, $others), 5);

        $gkA = collect($result['teamA'])->where('likes_goalie', true)->count();
        $gkB = collect($result['teamB'])->where('likes_goalie', true)->count();

        $this->assertSame(2, $gkA);
        $this->assertSame(2, $gkB);
    }

    public function test_goalies_do_not_break_score_balance(): void
    {
        $participants = [
            ['score' => 9.0, 'likes_goalie' => true],
            ['score' => 8.0, 'likes_goalie' => true],
            ['score' => 7.0, 'likes_goalie' => true],
            ['score' => 6.0, 'likes_goalie' => false],
            ['score' => 5.0, 'likes_goalie' => false],
            ['score' => 4.0, 'likes_goalie' => false],
            ['score' => 3.0, 'likes_goalie' => false],
            ['score' => 2.0, 'likes_goalie' => false],
            ['score' => 1.0, 'likes_goalie' => false],
            ['score' => 1.0, 'likes_goalie' => true],
        ];

        $result = $this->balancer->balance($participants, 5);

        $sumA = collect($result['teamA'])->sum('score');
        $sumB = collect($result['teamB'])->sum('score');

        $this->assertLessThanOrEqual(2.0, abs($sumA - $sumB));
    }

    public function test_each_dupla_pairs_the_weakest_with_the_strongest_and_is_split(): void
    {
        $participants = collect(range(1, 8))->map(fn ($s) => ['id' => (string) $s, 'score' => (float) $s])->all();

        $result = $this->balancer->balance($participants, 4);

        $teamA = collect($result['teamA'])->map(fn ($p) => (string) $p['id'])->all();
        $teamB = collect($result['teamB'])->map(fn ($p) => (string) $p['id'])->all();

        // Duplas 1+8, 2+7, 3+6 and 4+5, one member per team.
        foreach ([[1, 8], [2, 7], [3, 6], [4, 5]] as [$low, $high]) {
            $low = (string) $low;
            $high = (string) $high;

            $this->assertTrue(
                (in_array($low, $teamA, true) && in_array($high, $teamB, true))
                || (in_array($low, $teamB, true) && in_array($high, $teamA, true)),
                "Dupla {$low}+{$high} is not split across teams",
            );
        }

        $sumA = collect($result['teamA'])->sum('score');
        $sumB = collect($result['teamB'])->sum('score');

        $this->assertLessThanOrEqual(2.0, abs($sumA - $sumB));
    }

    public function test_a_strong_shooter_is_answered_by_a_strong_shooter(): void
    {
        $participants = [
            ['name' => 'shooter-strong', 'attributes' => $this->attributes(['shooting' => 10]), 'score' => 0.0],
            ['name' => 'shooter-close', 'attributes' => $this->attributes(['shooting' => 9]), 'score' => 0.0],
            ['name' => 'weak-3', 'attributes' => $this->attributes(['shooting' => 3]), 'score' => 0.0],
            ['name' => 'weak-2', 'attributes' => $this->attributes(['shooting' => 2]), 'score' => 0.0],
        ];

        $result = $this->balancer->balance($participants, 2);

        $shootersA = collect($result['teamA'])->where(fn ($p) => $p['attributes']['shooting'] >= 9)->count();
        $shootersB = collect($result['teamB'])->where(fn ($p) => $p['attributes']['shooting'] >= 9)->count();

        $this->assertSame(1, $shootersA);
        $this->assertSame(1, $shootersB);
    }

    public function test_the_teams_do_not_depend_on_signup_order(): void
    {
        $participants = [
            ['name' => 'one', 'attributes' => $this->attributes(['speed' => 10, 'skill' => 2]), 'score' => 0.0],
            ['name' => 'two', 'attributes' => $this->attributes(['speed' => 9, 'skill' => 4]), 'score' => 0.0],
            ['name' => 'three', 'attributes' => $this->attributes(['speed' => 7, 'skill' => 6]), 'score' => 0.0],
            ['name' => 'four', 'attributes' => $this->attributes(['speed' => 6, 'skill' => 9]), 'score' => 0.0],
            ['name' => 'five', 'attributes' => $this->attributes(['speed' => 3, 'skill' => 3]), 'score' => 0.0],
            ['name' => 'six', 'attributes' => $this->attributes(['speed' => 2, 'skill' => 8]), 'score' => 0.0],
        ];

        $sums = function (array $team): array {
            $result = [];

            foreach (['speed', 'skill', 'passing', 'shooting', 'defense'] as $attribute) {
                $result[$attribute] = collect($team)->sum(fn ($p) => $p['attributes'][$attribute]);
            }

            return $result;
        };

        $first = $this->balancer->balance($participants, 3);
        $second = $this->balancer->balance(array_reverse($participants), 3);

        $this->assertContains($sums($second['teamA']), [$sums($first['teamA']), $sums($first['teamB'])]);
    }

    public function test_odd_attendance_gives_the_extra_player_to_the_weaker_team(): void
    {
        $participants = [
            ['name' => 'a', 'score' => 10.0],
            ['name' => 'b', 'score' => 5.0],
            ['name' => 'c', 'score' => 5.0],
            ['name' => 'd', 'score' => 5.0],
            ['name' => 'e', 'score' => 1.0],
        ];

        $result = $this->balancer->balance($participants, 3);

        $teamA = collect($result['teamA']);
        $teamB = collect($result['teamB']);

        $sizes = [$teamA->count(), $teamB->count()];
        sort($sizes);
        $this->assertSame([2, 3], $sizes);

        $extraTeam = $teamA->contains('name', 'e') ? $teamA : $teamB;
        $otherTeam = $extraTeam === $teamA ? $teamB : $teamA;

        // Without the extra player the team that received it was the weaker one.
        $this->assertLessThanOrEqual($otherTeam->sum('score'), $extraTeam->sum('score') - 1.0);
    }
}
