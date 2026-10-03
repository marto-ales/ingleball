<?php

namespace App\Services;

use App\Models\MatchTeam;
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
        $settings = $this->settings->forGroup($match->group_id);
        $scorer = $this->scorer->forGroup($match->group_id);

        $entries = $match->entries()
            ->with(['user', 'user.players', 'guest'])
            ->where('role', 'going')
            ->get();

        $participants = [];
        $weights = $settings->weights();

        foreach ($entries as $entry) {
            if ($entry->user !== null) {
                $attributes = $scorer->attributesForUser($entry->user);

                $participants[] = [
                    'user_id' => $entry->user->id,
                    'guest_id' => null,
                    'name' => $entry->user->name,
                    'score' => $scorer->power($attributes, $weights),
                    'attributes' => $attributes,
                    'likes_goalie' => (bool) ($entry->user->playerFor($match->group_id)?->likes_goalie ?? false),
                ];
            } elseif ($entry->guest !== null) {
                $attributes = $scorer->attributesForGuest($entry->guest);

                $participants[] = [
                    'user_id' => null,
                    'guest_id' => $entry->guest->id,
                    'name' => $entry->guest->name,
                    'score' => $scorer->power($attributes, $weights),
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

        $settings = $this->settings->forGroup($match->group_id);

        $selected = array_slice($participants, 0, $size * 2);
        $result = $this->balancer->balance($selected, $size, [
            'weights' => $settings->weights(),
            'random_tie_break' => $settings->randomTieBreak(),
            'spread_goalies' => $settings->spreadGoalies(),
        ]);

        $match->teams()->delete();

        foreach ($result['teamA'] as $position => $participant) {
            $this->persist($match, 'A', $position, $participant);
        }

        foreach ($result['teamB'] as $position => $participant) {
            $this->persist($match, 'B', $position, $participant);
        }

        return $result;
    }

    /**
     * How alike the two players sitting on the same sheet row are, as a
     * percentage: 100 for the same profile, 0 for the widest gap the 0-10 scale
     * allows. It is the very distance the lineup ordering minimizes, so the
     * number the organizer reads is the one the algorithm worked with.
     */
    public function similarityPercent(?MatchTeam $rowA, ?MatchTeam $rowB, Partido $match): ?float
    {
        if ($rowA === null || $rowB === null) {
            return null;
        }

        $settings = $this->settings->forGroup($match->group_id);
        $scorer = $this->scorer->forGroup($match->group_id);
        $weights = $settings->weights();

        $player = function (MatchTeam $row) use ($scorer, $weights): ?array {
            if ($row->user !== null) {
                $attributes = $scorer->attributesForUser($row->user);
            } elseif ($row->guest !== null) {
                $attributes = $scorer->attributesForGuest($row->guest);
            } else {
                return null;
            }

            return ['score' => $scorer->power($attributes, $weights), 'attributes' => $attributes];
        };

        $playerA = $player($rowA);
        $playerB = $player($rowB);

        if ($playerA === null || $playerB === null) {
            return null;
        }

        return $this->balancer->similarityPercent($playerA, $playerB, $weights);
    }

    /**
     * The skills the sheet rows compare, heaviest first: row 1 is the first one
     * here, row 2 the second, and so on, which is the order the lineup pass
     * walks them in.
     *
     * @return array<int, string>
     */
    public function rowDimensions(Partido $match): array
    {
        return $this->balancer->dimensionsByWeight(
            $this->settings->forGroup($match->group_id)->weights(),
        );
    }

    private function persist(Partido $match, string $team, int $position, array $participant): void
    {
        $match->teams()->create([
            'team' => $team,
            'position' => $position,
            'user_id' => $participant['user_id'],
            'guest_id' => $participant['guest_id'],
        ]);
    }
}
