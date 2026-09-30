<?php

namespace App\Http\Controllers;

use App\Models\MatchResult;
use App\Models\Partido;
use App\Models\User;
use App\Services\Scorer;
use App\Services\StatsService;
use App\Support\ActiveGroup;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function __construct(
        private StatsService $stats,
        private Scorer $scorer,
        private ActiveGroup $activeGroup,
    ) {}

    public function index(Request $request): View
    {
        return view('stats.index', [
            'rows' => $this->stats->leaderboard($this->activeGroup->id()),
            'formWindow' => max(1, (int) config('balance.form_window')),
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $groupId = $this->activeGroup->id();

        $scorer = $this->scorer->forGroup($groupId);

        $user->loadMissing('players');

        $matches = $user->entries()
            ->where('role', 'going')
            ->whereHas('match', fn ($query) => $query
                ->where('group_id', $groupId)
                ->where('status', Partido::STATUS_FINISHED))
            ->with('match')
            ->get()
            ->map(fn ($entry) => $entry->match)
            ->filter()
            ->unique('id')
            ->sortByDesc('played_at')
            ->values();

        $mvp = MatchResult::where('mvp_user_id', $user->id)
            ->whereHas('match', fn ($query) => $query->where('group_id', $groupId))
            ->with('match')
            ->get();

        $orgEval = null;
        $evaluations = $user->evaluationsReceived()->where('group_id', $groupId)->get();
        if ($evaluations->isNotEmpty()) {
            $orgEval = [
                'speed' => round($evaluations->avg('speed'), 1),
                'skill' => round($evaluations->avg('skill'), 1),
                'passing' => round($evaluations->avg('passing'), 1),
                'shooting' => round($evaluations->avg('shooting'), 1),
                'defense' => round($evaluations->avg('defense'), 1),
                'goalkeeping' => round($evaluations->avg('goalkeeping'), 1),
            ];
        }

        return view('stats.show', [
            'player' => $user,
            'matches' => $matches,
            'mvp' => $mvp,
            'profile' => $scorer->attributesForUser($user),
            'goalkeeping' => $scorer->goalkeepingForUser($user),
            'form' => $scorer->formForUser($user),
            'orgEval' => $orgEval,
        ]);
    }
}
