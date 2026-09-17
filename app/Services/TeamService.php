<?php

namespace App\Services;

use App\Models\Partido;

class TeamService
{
    public function __construct(
        private Scorer $scorer,
        private TeamBalancer $balancer,
        private AlgorithmSettings $settings,
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
     * stable identity (user_id or guest_id), their name, per-attribute scores,
     * the weighted power derived from them and whether they like playing
     * goalkeeper.
     *
     * @return array<int, array{user_id: int|null, guest_id: int|null, name: string, score: float, attributes: array<string, float>, likes_goalie: bool}>
     */
    public function collect(Partido $match): array
    {
        $entries = $match->entries()
            ->with(['user', 'user.player', 'guest'])
            ->where('role', 'going')
            ->get();

        $participants = [];
        $weights = $this->settings->weights();

        foreach ($entries as $entry) {
            if ($entry->user !== null) {
                $attributes = $this->scorer->attributesForUser($entry->user);

                $participants[] = [
                    'user_id' => $entry->user->id,
                    'guest_id' => null,
                    'name' => $entry->user->name,
                    'score' => $this->scorer->power($attributes, $weights),
                    'attributes' => $attributes,
                    'likes_goalie' => (bool) ($entry->user->player?->likes_goalie ?? false),
                ];
            } elseif ($entry->guest !== null) {
                $attributes = $this->scorer->attributesForGuest($entry->guest);

                $participants[] = [
                    'user_id' => null,
                    'guest_id' => $entry->guest->id,
                    'name' => $entry->guest->name,
                    'score' => $this->scorer->power($attributes, $weights),
                    'attributes' => $attributes,
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
        $result = $this->balancer->balance($selected, $size, [
            'weights' => $this->settings->weights(),
            'random_tie_break' => $this->settings->randomTieBreak(),
            'spread_goalies' => $this->settings->spreadGoalies(),
        ]);

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
