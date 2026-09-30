<?php

namespace App\Http\Controllers;

use App\Models\Partido;
use App\Services\RecurringMatchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, RecurringMatchService $recurring): View
    {
        $recurring->ensureUpcoming();

        Partido::whereIn('status', [Partido::STATUS_OPEN, Partido::STATUS_LOCKED])
            ->where('played_at', '<', now())
            ->get()
            ->each(fn (Partido $match) => $match->autoFinish());

        $upcoming = Partido::with('creator')
            ->whereIn('status', [Partido::STATUS_OPEN, Partido::STATUS_LOCKED])
            ->where('played_at', '>=', now()->subDay())
            ->orderBy('played_at')
            ->take(10)
            ->get();

        $finished = Partido::where('status', Partido::STATUS_FINISHED)
            ->orderByDesc('played_at')
            ->get();

        $cancelled = Partido::where('status', Partido::STATUS_CANCELLED)
            ->orderByDesc('played_at')
            ->get();

        $myEntries = $request->user()
            ->entries()
            ->whereIn('match_id', $upcoming->pluck('id'))
            ->get()
            ->keyBy('match_id');

        $needsSelfEval = $request->user()->player === null
            || $request->user()->player->self_eval_completed_at === null;

        $needsEmail = blank($request->user()->email);

        return view('dashboard', compact('upcoming', 'finished', 'cancelled', 'myEntries', 'needsSelfEval', 'needsEmail'));
    }
}
