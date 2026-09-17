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
     * Above this many participants the exhaustive pairing gets expensive, so a
     * greedy nearest-neighbour matching is used instead. Real matches never
     * reach it: the generator only ever sends at most 12 players (6v6).
     */
    private const EXHAUSTIVE_LIMIT = 12;

    /**
     * Tolerance used to treat two floating point costs as a tie.
     */
    private const EPSILON = 1.0e-9;

    /**
     * Split participants into two teams so that every player has a rival of
     * similar characteristics on the other team. Players are first paired by
     * how alike their attribute vectors are (speed weighing the most, then
     * skill), then each pair is split one per team choosing the orientation
     * that keeps every characteristic as even as possible, preferring a polite
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
        $leftover = null;

        if ($count % 2 === 1) {
            // Odd attendance: the weakest player waits and later joins the
            // weakest team, so the extra man compensates the gap.
            usort($indices, fn (int $a, int $b): int => $this->power($profiles[$b], $weights) <=> $this->power($profiles[$a], $weights));
            $leftover = array_pop($indices);
        }

        $pairs = $this->pairUp(array_values($indices), $profiles, $weights, $random);
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
     * Distance between two players: how differently they are built.
     */
    private function distance(array $a, array $b, array $weights): float
    {
        $distance = 0.0;

        foreach ($weights as $dimension => $weight) {
            $distance += $weight * abs(($a[$dimension] ?? 0) - ($b[$dimension] ?? 0));
        }

        return $distance;
    }

    /**
     * Pair the indices so the total distance between partners is minimal, which
     * gives every player the closest possible rival. Exhaustive with memoization
     * up to EXHAUSTIVE_LIMIT players, greedy beyond that.
     *
     * @param  array<int, int>  $indices
     * @return array<int, array{0: int, 1: int}>
     */
    private function pairUp(array $indices, array $profiles, array $weights, bool $random): array
    {
        $indices = array_values($indices);

        if (count($indices) > self::EXHAUSTIVE_LIMIT) {
            return $this->pairUpGreedy($indices, $profiles, $weights);
        }

        $memo = [];

        return $this->bestMatching($indices, $profiles, $weights, $random, $memo);
    }

    private function bestMatching(array $indices, array $profiles, array $weights, bool $random, array &$memo): array
    {
        $indices = array_values($indices);

        if (count($indices) < 2) {
            return [];
        }

        $key = implode(',', $indices);

        if (isset($memo[$key])) {
            return $memo[$key];
        }

        $first = $indices[0];
        $rest = array_slice($indices, 1);
        $best = [];
        $bestCost = INF;

        foreach ($rest as $position => $partner) {
            $remaining = $rest;
            unset($remaining[$position]);

            $sub = $this->bestMatching(array_values($remaining), $profiles, $weights, $random, $memo);
            $cost = $this->distance($profiles[$first], $profiles[$partner], $weights)
                + $this->matchingCost($sub, $profiles, $weights);

            if ($cost < $bestCost - self::EPSILON) {
                $bestCost = $cost;
                $best = array_merge([[$first, $partner]], $sub);
            } elseif ($random && abs($cost - $bestCost) <= self::EPSILON && mt_rand(0, 1) === 1) {
                $best = array_merge([[$first, $partner]], $sub);
            }
        }

        return $memo[$key] = $best;
    }

    /**
     * @param  array<int, array{0: int, 1: int}>  $pairs
     */
    private function matchingCost(array $pairs, array $profiles, array $weights): float
    {
        $cost = 0.0;

        foreach ($pairs as [$a, $b]) {
            $cost += $this->distance($profiles[$a], $profiles[$b], $weights);
        }

        return $cost;
    }

    /**
     * @param  array<int, int>  $indices
     * @return array<int, array{0: int, 1: int}>
     */
    private function pairUpGreedy(array $indices, array $profiles, array $weights): array
    {
        $pairs = [];

        while (count($indices) >= 2) {
            $first = array_shift($indices);
            $nearest = null;
            $nearestCost = INF;

            foreach ($indices as $position => $candidate) {
                $cost = $this->distance($profiles[$first], $profiles[$candidate], $weights);

                if ($cost < $nearestCost) {
                    $nearestCost = $cost;
                    $nearest = $position;
                }
            }

            $pairs[] = [$first, $indices[$nearest]];
            unset($indices[$nearest]);
            $indices = array_values($indices);
        }

        return $pairs;
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
