<?php

namespace App\Services;

use App\Models\Goal;
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
        $totalMatches = Partido::count();

        return User::with('player')
            ->orderBy('id')
            ->get()
            ->map(function (User $user) use ($totalMatches): array {
                $matches = $user->entries()->where('role', 'going')->count();
                $form = $this->scorer->formForUser($user);

                return [
                    'user' => $user,
                    'score' => $this->scorer->scoreForUser($user, $form),
                    'matches' => $matches,
                    'attendance' => $totalMatches > 0 ? round($matches / $totalMatches * 100) : 0,
                    'goals' => Goal::where('scorer_user_id', $user->id)->count(),
                    'assists' => Goal::where('assister_user_id', $user->id)->count(),
                    'mvp' => MatchResult::where('mvp_user_id', $user->id)->count(),
                    'general' => $form['general'],
                    'form' => $form['multiplier'],
                    'goalkeeping' => $this->scorer->goalkeepingForUser($user),
                ];
            })
            ->sortByDesc('score')
            ->values();
    }
}
