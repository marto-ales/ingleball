<?php

namespace App\Services;

class TeamBalancer
{
    /**
     * When two orientations leave the same attribute imbalance, this makes the
     * one that spreads goalkeepers more evenly win. It is large on purpose:
     * having a goalie on each team matters more than a slightly better balance.
     */
    private const GOALIE_PENALTY = 100.0;

    /**
     * Tolerance used to treat two floating point costs as a tie.
     */
    private const EPSILON = 1.0e-9;

    /**
     * Split participants into two teams by building duplas: the lowest rated
     * player is paired with the highest, the next lowest with the next highest,
     * and so on. Each dupla therefore carries a similar combined power, and
     * every dupla contributes one player to each team. The orientation that
     * keeps the totals as even as possible is chosen, preferring a polite
     * goalkeeper on each side.
     *
     * Participants are plain arrays with a 'score' and, when available, an
     * 'attributes' map and a 'likes_goalie' flag. When no attributes are given
     * the score itself is used as a single dimension, keeping the old behavior.
     *
     * Options (all optional):
     *  - weights: attribute weights used to compare players;
     *  - random_tie_break: pick randomly among equally good solutions;
     *  - spread_goalies: try to keep one goalie-liker per team.
     *
     * @param  array<int, array{score: float, attributes?: array<string, float>, likes_goalie?: bool}>  $participants
     * @param  array{weights?: array<string, float>, random_tie_break?: bool, spread_goalies?: bool}  $options
     * @return array{teamA: array, teamB: array}
     */
    public function balance(array $participants, int $size, array $options = []): array
    {
        $participants = array_values($participants);
        $count = count($participants);

        if ($count === 0) {
            return ['teamA' => [], 'teamB' => []];
        }

        $random = (bool) ($options['random_tie_break'] ?? false);
        $spreadGoalies = (bool) ($options['spread_goalies'] ?? true);
        $weights = $this->weights($participants, $options['weights'] ?? null);

        $profiles = [];

        foreach ($participants as $index => $participant) {
            $profiles[$index] = $this->profile($participant, $weights);
        }

        $indices = range(0, $count - 1);
        $this->sortByPower($indices, $participants, $profiles, $weights);
        $leftover = null;

        if ($count % 2 === 1) {
            // Odd attendance: the weakest player waits and later joins the
            // weakest team, so the extra man compensates the gap.
            $leftover = array_shift($indices);
        }

        $pairs = $this->duplas($indices);
        [$teamA, $teamB] = $this->orient($pairs, $participants, $profiles, $weights, $random, $spreadGoalies);

        if ($leftover !== null) {
            $sumA = $this->totalPower($teamA, $profiles, $weights);
            $sumB = $this->totalPower($teamB, $profiles, $weights);

            if ($sumA === $sumB) {
                $toA = $random ? (bool) mt_rand(0, 1) : true;
            } else {
                $toA = $sumA < $sumB;
            }

            if ($toA) {
                $teamA[] = $leftover;
            } else {
                $teamB[] = $leftover;
            }
        }

        return [
            'teamA' => array_map(fn (int $i) => $participants[$i], $teamA),
            'teamB' => array_map(fn (int $i) => $participants[$i], $teamB),
        ];
    }

    /**
     * Dimensions used to compare players: the configured attributes with their
     * weights when every participant carries them, otherwise the plain score.
     *
     * @param  array<string, float>|null  $configured
     * @return array<string, float>
     */
    private function weights(array $participants, ?array $configured): array
    {
        foreach ($participants as $participant) {
            if (! isset($participant['attributes']) || ! is_array($participant['attributes'])) {
                return ['__score' => 1.0];
            }
        }

        return $configured ?? (array) config('balance.attribute_weights');
    }

    private function profile(array $participant, array $weights): array
    {
        if (isset($weights['__score'])) {
            return ['__score' => (float) ($participant['score'] ?? 5)];
        }

        return array_map('floatval', $participant['attributes']);
    }

    private function power(array $profile, array $weights): float
    {
        $power = 0.0;

        foreach ($weights as $dimension => $weight) {
            $power += $weight * ($profile[$dimension] ?? 0);
        }

        return $power;
    }

    /**
     * @param  array<int, int>  $indices
     */
    private function totalPower(array $indices, array $profiles, array $weights): float
    {
        $total = 0.0;

        foreach ($indices as $index) {
            $total += $this->power($profiles[$index], $weights);
        }

        return $total;
    }

    /**
     * Pair the players by extremes: the lowest rated with the highest, then the
     * next lowest with the next highest, and so on. Each dupla carries a
     * similar combined power, so sending one member to each team keeps both
     * close in total score.
     *
     * @param  array<int, int>  $indices  sorted ascending by power
     * @return array<int, array{0: int, 1: int}>
     */
    private function duplas(array $indices): array
    {
        $pairs = [];
        $last = count($indices) - 1;

        for ($first = 0; $first < $last; $first++, $last--) {
            $pairs[] = [$indices[$first], $indices[$last]];
        }

        return $pairs;
    }

    /**
     * Sort the indices by the players' power (their score), weakest first. Ties
     * are broken by name and then by position, so the order never depends on
     * how the list was fed in.
     *
     * @param  array<int, int>  $indices
     */
    private function sortByPower(array &$indices, array $participants, array $profiles, array $weights): void
    {
        usort($indices, function (int $a, int $b) use ($participants, $profiles, $weights): int {
            $byPower = $this->power($profiles[$a], $weights) <=> $this->power($profiles[$b], $weights);

            if ($byPower !== 0) {
                return $byPower;
            }

            $nameA = (string) ($participants[$a]['name'] ?? $participants[$a]['id'] ?? $a);
            $nameB = (string) ($participants[$b]['name'] ?? $participants[$b]['id'] ?? $b);
            $byName = strcmp($nameA, $nameB);

            if ($byName !== 0) {
                return $byName;
            }

            return $a <=> $b;
        });
    }

    /**
     * For every pair, decide which player goes to each team. All orientations
     * are tried and the ones minimizing the sum of per-characteristic gaps win;
     * with random tie-breaking one of those is picked at random, otherwise the
     * first one. The goalie spread acts as a tie-breaker so each team tends to
     * have one, unless it is disabled.
     *
     * @param  array<int, array{0: int, 1: int}>  $pairs
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private function orient(array $pairs, array $participants, array $profiles, array $weights, bool $random, bool $spreadGoalies): array
    {
        $goalieWeight = $spreadGoalies ? self::GOALIE_PENALTY : 0.0;
        $bestCost = INF;
        $candidates = [];
        $combinations = 1 << count($pairs);

        for ($mask = 0; $mask < $combinations; $mask++) {
            $candidateA = [];
            $candidateB = [];

            foreach ($pairs as $position => [$left, $right]) {
                if ($mask & (1 << $position)) {
                    $candidateA[] = $left;
                    $candidateB[] = $right;
                } else {
                    $candidateA[] = $right;
                    $candidateB[] = $left;
                }
            }

            $cost = $this->imbalance($candidateA, $candidateB, $profiles, $weights)
                + $goalieWeight * abs($this->goalies($candidateA, $participants) - $this->goalies($candidateB, $participants));

            if ($cost < $bestCost - self::EPSILON) {
                $bestCost = $cost;
                $candidates = [[$candidateA, $candidateB]];
            } elseif (abs($cost - $bestCost) <= self::EPSILON) {
                $candidates[] = [$candidateA, $candidateB];
            }
        }

        $chosen = $random ? $candidates[array_rand($candidates)] : $candidates[0];

        return [$chosen[0], $chosen[1]];
    }

    /**
     * Sum of the per-characteristic gaps between the two teams.
     *
     * @param  array<int, int>  $teamA
     * @param  array<int, int>  $teamB
     */
    private function imbalance(array $teamA, array $teamB, array $profiles, array $weights): float
    {
        $cost = 0.0;

        foreach ($weights as $dimension => $weight) {
            $sumA = 0.0;
            $sumB = 0.0;

            foreach ($teamA as $index) {
                $sumA += $profiles[$index][$dimension] ?? 0;
            }

            foreach ($teamB as $index) {
                $sumB += $profiles[$index][$dimension] ?? 0;
            }

            $cost += $weight * abs($sumA - $sumB);
        }

        return $cost;
    }

    /**
     * @param  array<int, int>  $indices
     */
    private function goalies(array $indices, array $participants): int
    {
        $goalies = 0;

        foreach ($indices as $index) {
            if (! empty($participants[$index]['likes_goalie'])) {
                $goalies++;
            }
        }

        return $goalies;
    }
}
