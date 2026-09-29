<?php

namespace App\Services;

use App\Models\Group;
use App\Models\MatchResult;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Support\Collection;

class StatsService
{
    public function __construct(private Scorer $scorer) {}

    /**
     * Leaderboard of registered players for the given group, best composite score first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function leaderboard(?int $groupId = null): Collection
    {
        $groupId ??= auth()->check()
            ? auth()->user()->group_id
            : Group::query()->orderBy('id')->value('id');

        $totalMatches = Partido::where('group_id', $groupId)
            ->where('status', Partido::STATUS_FINISHED)
            ->count();

        $scorer = $this->scorer->forGroup($groupId);

        return User::with('player')
            ->where('group_id', $groupId)
            ->orderBy('id')
            ->get()
            ->map(function (User $user) use ($groupId, $scorer, $totalMatches): array {
                $played = $user->entries()
                    ->where('role', 'going')
                    ->whereHas('match', fn ($q) => $q
                        ->where('status', Partido::STATUS_FINISHED)
                        ->where('group_id', $groupId))
                    ->count();
                $form = $scorer->formForUser($user);

                return [
                    'user' => $user,
                    'score' => $scorer->scoreForUser($user, $form),
                    'matches' => $played,
                    'attendance' => $totalMatches > 0 ? round($played / $totalMatches * 100) : 0,
                    'mvp' => MatchResult::where('mvp_user_id', $user->id)
                        ->whereHas('match', fn ($q) => $q->where('group_id', $groupId))
                        ->count(),
                    'general' => $form['general'],
                    'form' => $form['multiplier'],
                    'rendimiento' => $scorer->ratingDetailsForUser($user),
                    'goalkeeping' => $scorer->goalkeepingForUser($user),
                ];
            })
            ->sortByDesc('score')
            ->values();
    }
}
