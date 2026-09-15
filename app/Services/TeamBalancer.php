<?php

namespace App\Services;

class TeamBalancer
{
    /**
     * Split participants (each carrying a 'score') into two balanced teams of
     * $size players, keeping the two total scores as close as possible.
     *
     * @param  array<int, array{score: float}>  $participants
     * @return array{teamA: array, teamB: array}
     */
    public function balance(array $participants, int $size): array
    {
        usort($participants, static fn (array $a, array $b) => $b['score'] <=> $a['score']);

        $teamA = [];
        $teamB = [];
        $sumA = 0.0;
        $sumB = 0.0;

        foreach ($participants as $participant) {
            $fillA = count($teamA) < $size;
            $fillB = count($teamB) < $size;

            $toA = match (true) {
                $fillA && $fillB => $sumA <= $sumB,
                $fillA => true,
                default => false,
            };

            if ($toA) {
                $teamA[] = $participant;
                $sumA += $participant['score'];
            } else {
                $teamB[] = $participant;
                $sumB += $participant['score'];
            }
        }

        return ['teamA' => $teamA, 'teamB' => $teamB];
    }
}
