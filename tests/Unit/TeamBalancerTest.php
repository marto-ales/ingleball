<?php

namespace Tests\Unit;

use App\Services\TeamBalancer;
use PHPUnit\Framework\TestCase;

final class TeamBalancerTest extends TestCase
{
    private TeamBalancer $balancer;

    protected function setUp(): void
    {
        $this->balancer = new TeamBalancer();
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
}
