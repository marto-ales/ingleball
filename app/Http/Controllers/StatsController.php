<?php

namespace App\Http\Controllers;

use App\Models\MatchResult;
use App\Models\User;
use App\Services\Scorer;
use App\Services\StatsService;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function __construct(private StatsService $stats, private Scorer $scorer) {}

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

        $mvp = MatchResult::where('mvp_user_id', $user->id)->with('match')->get();

        return view('stats.show', [
            'player' => $user,
            'matches' => $matches,
            'mvp' => $mvp,
            'profile' => $this->scorer->attributesForUser($user),
            'goalkeeping' => $this->scorer->goalkeepingForUser($user),
            'form' => $this->scorer->formForUser($user),
        ]);
    }
}
