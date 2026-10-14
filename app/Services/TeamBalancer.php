<?php

namespace App\Services;

class TeamBalancer
{
    /**
     * Tolerance used to treat two floating point costs as a tie.
     */
    private const EPSILON = 1.0e-9;

    /**
     * Split participants into two teams by building duplas: the lowest rated
     * player is paired with the highest, the next lowest with the next highest,
     * and so on. Each dupla therefore carries a similar combined power, and
     * every dupla contributes one player to each team. The orientation that
     * keeps the teams as even as possible per attribute is chosen; a polite
     * goalkeeper on each side only breaks ties among equally balanced ones.
     *
     * Once each team is settled, its players are lined up so every row compares
     * like with like: walking the characteristics by importance, the best player
     * still unpaired on one side faces the best one still unpaired on the other,
     * so the fastest of each team heads the sheet, the most skillful of each
     * team is next, and the defense matchup comes last. That pass only reorders
     * the players already assigned, so the balance computed above is left
     * untouched.
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

        [$teamA, $teamB] = $this->orderByRanking($teamA, $teamB, $participants, $profiles, $weights);

        return [
            'teamA' => array_map(fn (int $i) => $participants[$i], $teamA),
            'teamB' => array_map(fn (int $i) => $participants[$i], $teamB),
        ];
    }

    /**
     * Put two teams already picked into sheet order, and nothing else: who is
     * on each team is left alone, this decides the row each player is listed
     * in. The same order the lineup pass uses, exposed so a stored team can be
     * re-listed with the current settings without regenerating it.
     *
     * @param  array<int, array{score: float, attributes?: array<string, float>, likes_goalie?: bool}>  $teamA
     * @param  array<int, array{score: float, attributes?: array<string, float>, likes_goalie?: bool}>  $teamB
     * @param  array<string, float>  $weights
     * @return array{teamA: array, teamB: array}
     */
    public function arrange(array $teamA, array $teamB, array $weights): array
    {
        $teamA = array_values($teamA);
        $teamB = array_values($teamB);
        $participants = [...$teamA, ...$teamB];
        $profiles = [];

        foreach ($participants as $index => $participant) {
            $profiles[$index] = $this->profile($participant, $weights);
        }

        $countA = count($teamA);
        $indicesA = $countA > 0 ? range(0, $countA - 1) : [];
        $indicesB = count($teamB) > 0 ? range($countA, count($participants) - 1) : [];

        [$orderA, $orderB] = $this->orderByRanking($indicesA, $indicesB, $participants, $profiles, $weights);

        return [
            'teamA' => array_map(fn (int $i) => $participants[$i], $orderA),
            'teamB' => array_map(fn (int $i) => $participants[$i], $orderB),
        ];
    }

    /**
     * Line up both teams so every row compares like with like: going through
     * the characteristics by importance, the best player still unpaired on one
     * side faces the best one still unpaired on the other. The fastest of A
     * therefore meets the fastest of B, the most skillful meets the most
     * skillful, the best in defense meets the best in defense, and so on.
     *
     * The balance is untouched: the same players stay on the same team, this
     * only decides the order they are listed in.
     *
     * @param  array<int, int>  $teamA
     * @param  array<int, int>  $teamB
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private function orderByRanking(array $teamA, array $teamB, array $participants, array $profiles, array $weights): array
    {
        $pendingA = $this->sortedByName($teamA, $participants);
        $pendingB = $this->sortedByName($teamB, $participants);
        $orderedA = [];
        $orderedB = [];

        foreach ($this->dimensionsByWeight($weights) as $dimension) {
            $best = $this->bestOf($pendingA, $dimension, $profiles);

            if ($best !== null) {
                $orderedA[] = $best;
                $pendingA = array_values(array_filter($pendingA, fn (int $i): bool => $i !== $best));
            }

            $best = $this->bestOf($pendingB, $dimension, $profiles);

            if ($best !== null) {
                $orderedB[] = $best;
                $pendingB = array_values(array_filter($pendingB, fn (int $i): bool => $i !== $best));
            }
        }

        // More players than characteristics, or an odd attendance leaving one
        // team a man over: whoever is left keeps a row at the bottom.
        return [
            [...$orderedA, ...array_values($pendingA)],
            [...$orderedB, ...array_values($pendingB)],
        ];
    }

    /**
     * The characteristics to compare by, heaviest first: the rows of the sheet
     * follow the importance each skill has. Equal weights keep the order they
     * are configured in. Public so the match sheet can label each row with the
     * very skill it compares.
     *
     * @param  array<string, float>  $weights
     * @return array<int, string>
     */
    public function dimensionsByWeight(array $weights): array
    {
        $dimensions = array_keys($weights);

        usort($dimensions, fn (string $a, string $b): int => ($weights[$b] ?? 0.0) <=> ($weights[$a] ?? 0.0));

        return $dimensions;
    }

    /**
     * The highest player of a team in one characteristic, ties going to the
     * first one by name so the answer never depends on the signup order.
     *
     * @param  array<int, int>  $pending
     * @param  array<int, array>  $profiles
     */
    private function bestOf(array $pending, string $dimension, array $profiles): ?int
    {
        $best = null;
        $bestValue = -INF;

        foreach ($pending as $index) {
            $value = (float) ($profiles[$index][$dimension] ?? 0.0);

            if ($best === null || $value > $bestValue + self::EPSILON) {
                $best = $index;
                $bestValue = $value;
            }
        }

        return $best;
    }

    /**
     * @param  array<int, int>  $indices
     * @param  array<int, array>  $participants
     * @return array<int, int>
     */
    private function sortedByName(array $indices, array $participants): array
    {
        usort($indices, function (int $a, int $b) use ($participants): int {
            $byName = strcmp($this->nameOf($a, $participants), $this->nameOf($b, $participants));

            return $byName !== 0 ? $byName : $a <=> $b;
        });

        return $indices;
    }

    /**
     * @param  array<int, array>  $participants
     */
    private function nameOf(int $index, array $participants): string
    {
        return (string) ($participants[$index]['name'] ?? $participants[$index]['id'] ?? $index);
    }

    /**
     * How alike two players are, as a cost to minimize: the weighted gap on
     * every attribute plus the gap on their overall score. This is the distance
     * the lineup is ordered by, exposed so the match sheet can show the same
     * number the algorithm used.
     *
     * @param  array{score: float, attributes?: array<string, float>}  $playerA
     * @param  array{score: float, attributes?: array<string, float>}  $playerB
     * @param  array<string, float>|null  $weights
     */
    public function similarityBetween(array $playerA, array $playerB, ?array $weights = null): float
    {
        $weights ??= (array) config('balance.attribute_weights');
        $attributesA = (array) ($playerA['attributes'] ?? []);
        $attributesB = (array) ($playerB['attributes'] ?? []);

        $cost = 0.0;

        foreach ($weights as $dimension => $weight) {
            $gap = abs((float) ($attributesA[$dimension] ?? 0.0) - (float) ($attributesB[$dimension] ?? 0.0));
            $cost += (float) $weight * $gap;
        }

        return $cost + abs((float) ($playerA['score'] ?? 0.0) - (float) ($playerB['score'] ?? 0.0));
    }

    /**
     * The same distance as a percentage to read on a sheet: 100 when the two
     * profiles match, and 0 when they are as far apart as the scale allows.
     *
     * @param  array{score: float, attributes?: array<string, float>}  $playerA
     * @param  array{score: float, attributes?: array<string, float>}  $playerB
     * @param  array<string, float>|null  $weights
     */
    public function similarityPercent(array $playerA, array $playerB, ?array $weights = null): float
    {
        $weights ??= (array) config('balance.attribute_weights');
        $widest = $this->widestGap($weights);

        if ($widest <= 0.0) {
            return 100.0;
        }

        $similarity = 100.0 * (1.0 - $this->similarityBetween($playerA, $playerB, $weights) / $widest);

        return round(max(0.0, min(100.0, $similarity)));
    }

    /**
     * The largest distance two players can be apart: attributes and overall
     * score all live on a 0-10 scale, so ten points on every weighted attribute
     * plus the ten points of score the same gap adds up to.
     *
     * @param  array<string, float>  $weights
     */
    private function widestGap(array $weights): float
    {
        $total = 0.0;

        foreach ($weights as $weight) {
            $total += (float) $weight;
        }

        return 20.0 * $total;
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

            $byName = strcmp($this->nameOf($a, $participants), $this->nameOf($b, $participants));

            return $byName !== 0 ? $byName : $a <=> $b;
        });
    }

    /**
     * For every pair, decide which player goes to each team. All orientations
     * are tried and the ones minimizing the sum of per-characteristic gaps win;
     * the goalkeeper spread only breaks ties among equally balanced ones, so it
     * can never trade away balance for a goalie on each side. With random
     * tie-breaking one of the equally good solutions is picked at random,
     * otherwise the first one.
     *
     * @param  array<int, array{0: int, 1: int}>  $pairs
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private function orient(array $pairs, array $participants, array $profiles, array $weights, bool $random, bool $spreadGoalies): array
    {
        $bestImbalance = INF;
        $bestGoalies = INF;
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

            $imbalance = $this->imbalance($candidateA, $candidateB, $profiles, $weights);
            $goaliesGap = $spreadGoalies
                ? abs($this->goalies($candidateA, $participants) - $this->goalies($candidateB, $participants))
                : 0;

            if ($imbalance < $bestImbalance - self::EPSILON) {
                // Clearly more balanced: previous candidates lose.
                $bestImbalance = $imbalance;
                $bestGoalies = $goaliesGap;
                $candidates = [[$candidateA, $candidateB]];

                continue;
            }

            if (abs($imbalance - $bestImbalance) > self::EPSILON) {
                continue;
            }

            // Same balance: the goalie spread is only a tie-breaker.
            if ($goaliesGap < $bestGoalies - self::EPSILON) {
                $bestGoalies = $goaliesGap;
                $candidates = [[$candidateA, $candidateB]];
            } elseif (abs($goaliesGap - $bestGoalies) <= self::EPSILON) {
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
