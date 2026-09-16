<?php

namespace App\Services;

use App\Models\Partido;

class TeamService
{
    public function __construct(
        private Scorer $scorer,
        private TeamBalancer $balancer,
    ) {}

    public function chooseTeamSize(int $goingCount): int
    {
        foreach ((array) config('balance.sizes') as $size) {
            if ($goingCount >= $size * 2) {
                return (int) $size;
            }
        }

        return 2;
    }

    /**
     * Collect the "going" participants of a match as plain arrays carrying a
     * stable identity (user_id or guest_id), their name, composite score and
     * whether they like playing goalkeeper.
     *
     * @return array<int, array{user_id: int|null, guest_id: int|null, name: string, score: float, likes_goalie: bool}>
     */
    public function collect(Partido $match): array
    {
        $entries = $match->entries()
            ->with(['user', 'user.player', 'guest'])
            ->where('role', 'going')
            ->get();

        $participants = [];

        foreach ($entries as $entry) {
            if ($entry->user !== null) {
                $participants[] = [
                    'user_id' => $entry->user->id,
                    'guest_id' => null,
                    'name' => $entry->user->name,
                    'score' => $this->scorer->forUser($entry->user),
                    'likes_goalie' => (bool) ($entry->user->player?->likes_goalie ?? false),
                ];
            } elseif ($entry->guest !== null) {
                $participants[] = [
                    'user_id' => null,
                    'guest_id' => $entry->guest->id,
                    'name' => $entry->guest->name,
                    'score' => $this->scorer->forGuest($entry->guest),
                    'likes_goalie' => false,
                ];
            }
        }

        return $participants;
    }

    /**
     * Generate the balanced teams for a match (5v5, or smaller as attendance
     * falls short) and persist them.
     *
     * @return array{teamA: array, teamB: array}
     */
    public function generate(Partido $match, ?int $preferredSize = null): array
    {
        $participants = $this->collect($match);
        $autoSize = $this->chooseTeamSize(count($participants));
        $size = $preferredSize !== null ? min($preferredSize, $autoSize) : $autoSize;

        $selected = array_slice($participants, 0, $size * 2);
        $result = $this->balancer->balance($selected, $size);

        $match->teams()->delete();

        foreach ($result['teamA'] as $participant) {
            $this->persist($match, 'A', $participant);
        }

        foreach ($result['teamB'] as $participant) {
            $this->persist($match, 'B', $participant);
        }

        return $result;
    }

    private function persist(Partido $match, string $team, array $participant): void
    {
        $match->teams()->create([
            'team' => $team,
            'user_id' => $participant['user_id'],
            'guest_id' => $participant['guest_id'],
        ]);
    }
}
