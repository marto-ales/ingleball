<?php

namespace App\Http\Controllers;

use App\Models\Goal;
use App\Models\MatchResult;
use App\Models\User;
use App\Services\StatsService;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function __construct(private StatsService $stats)
    {
    }

    public function index(): View
    {
        return view('stats.index', ['rows' => $this->stats->leaderboard()]);
    }

    public function show(User $user): View
    {
        $user->load('player');

        $matches = $user->entries()
            ->where('role', 'going')
            ->with('match')
            ->get()
            ->map(fn ($entry) => $entry->match)
            ->filter()
            ->unique('id')
            ->sortByDesc('played_at')
            ->values();

        $goals = Goal::where('scorer_user_id', $user->id)->with('match')->orderByDesc('created_at')->get();
        $mvp = MatchResult::where('mvp_user_id', $user->id)->with('match')->get();

        return view('stats.show', [
            'player' => $user,
            'matches' => $matches,
            'goals' => $goals,
            'mvp' => $mvp,
        ]);
    }
}
