<?php

namespace App\Services;

class TeamBalancer
{
    /**
     * Split participants (each carrying a 'score') into two balanced teams of
     * $size players, keeping the two total scores as close as possible while
     * preferring to spread goalie players so every team gets at least one.
     *
     * @param  array<int, array{score: float, likes_goalie?: bool}>  $participants
     * @return array{teamA: array, teamB: array}
     */
    public function balance(array $participants, int $size): array
    {
        usort($participants, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $teamA = [];
        $teamB = [];
        $sumA = 0.0;
        $sumB = 0.0;
        $goaliesA = 0;
        $goaliesB = 0;

        foreach ($participants as $participant) {
            $fillA = count($teamA) < $size;
            $fillB = count($teamB) < $size;

            if ($fillA && $fillB) {
                $toA = $this->pickTeam($participant, $sumA, $sumB, $goaliesA, $goaliesB);
            } else {
                $toA = $fillA;
            }

            if ($toA) {
                $teamA[] = $participant;
                $sumA += $participant['score'];

                if (! empty($participant['likes_goalie'])) {
                    $goaliesA++;
                }
            } else {
                $teamB[] = $participant;
                $sumB += $participant['score'];

                if (! empty($participant['likes_goalie'])) {
                    $goaliesB++;
                }
            }
        }

        return ['teamA' => $teamA, 'teamB' => $teamB];
    }

    /**
     * Decide the target team when both still have capacity. Goalie players are
     * steered to the team that has fewer of them so every team gets at least
     * one; everyone else is assigned by pure score balance.
     */
    private function pickTeam(
        array $participant,
        float $sumA,
        float $sumB,
        int $goaliesA,
        int $goaliesB
    ): bool {
        $score = $participant['score'];
        $imbalanceA = abs(($sumA + $score) - $sumB);
        $imbalanceB = abs($sumA - ($sumB + $score));

        if (! empty($participant['likes_goalie'])) {
            if ($goaliesA === $goaliesB) {
                return $imbalanceA <= $imbalanceB;
            }

            return $goaliesA < $goaliesB;
        }

        return $imbalanceA <= $imbalanceB;
    }
}