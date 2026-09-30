<?php

namespace App\Http\Controllers;

use App\Models\Partido;
use App\Services\RecurringMatchService;
use App\Support\ActiveGroup;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private ActiveGroup $activeGroup) {}

    public function index(Request $request, RecurringMatchService $recurring): View
    {
        $groupId = $this->activeGroup->id();

        $recurring->ensureUpcoming($groupId);

        Partido::where('group_id', $groupId)
            ->whereIn('status', [Partido::STATUS_OPEN, Partido::STATUS_LOCKED])
            ->where('played_at', '<', now())
            ->get()
            ->each(fn (Partido $match) => $match->autoFinish());

        $upcoming = Partido::with('creator')
            ->where('group_id', $groupId)
            ->whereIn('status', [Partido::STATUS_OPEN, Partido::STATUS_LOCKED])
            ->where('played_at', '>=', now()->subDay())
            ->orderBy('played_at')
            ->take(10)
            ->get();

        $finished = Partido::where('group_id', $groupId)
            ->where('status', Partido::STATUS_FINISHED)
            ->orderByDesc('played_at')
            ->get();

        $cancelled = Partido::where('group_id', $groupId)
            ->where('status', Partido::STATUS_CANCELLED)
            ->orderByDesc('played_at')
            ->get();

        $myEntries = $request->user()
            ->entries()
            ->whereIn('match_id', $upcoming->pluck('id'))
            ->get()
            ->keyBy('match_id');

        $profile = $request->user()->playerFor($groupId);
        $needsSelfEval = $profile === null || $profile->self_eval_completed_at === null;

        $needsEmail = blank($request->user()->email);

        return view('dashboard', compact('upcoming', 'finished', 'cancelled', 'myEntries', 'needsSelfEval', 'needsEmail'));
    }
}
