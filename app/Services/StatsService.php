<?php

namespace App\Services;

use App\Models\MatchResult;
use App\Models\Partido;
use App\Models\User;
use Illuminate\Support\Collection;

class StatsService
{
    public function __construct(private Scorer $scorer) {}

    /**
     * Leaderboard of registered players, best composite score first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function leaderboard(): Collection
    {
        $totalMatches = Partido::where('status', Partido::STATUS_FINISHED)->count();

        return User::with('player')
            ->orderBy('id')
            ->get()
            ->map(function (User $user) use ($totalMatches): array {
                $played = $user->entries()
                    ->where('role', 'going')
                    ->whereHas('match', fn ($q) => $q->where('status', Partido::STATUS_FINISHED))
                    ->count();
                $form = $this->scorer->formForUser($user);

                return [
                    'user' => $user,
                    'score' => $this->scorer->scoreForUser($user, $form),
                    'matches' => $played,
                    'attendance' => $totalMatches > 0 ? round($played / $totalMatches * 100) : 0,
                    'mvp' => MatchResult::where('mvp_user_id', $user->id)->count(),
                    'general' => $form['general'],
                    'form' => $form['multiplier'],
                    'rendimiento' => $this->scorer->ratingDetailsForUser($user),
                    'goalkeeping' => $this->scorer->goalkeepingForUser($user),
                ];
            })
            ->sortByDesc('score')
            ->values();
    }
}
