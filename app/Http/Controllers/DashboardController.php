<?php

namespace App\Http\Controllers;

use App\Models\Partido;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $upcoming = Partido::whereIn('status', [Partido::STATUS_OPEN, Partido::STATUS_LOCKED])
            ->where('played_at', '>=', now()->subDay())
            ->orderBy('played_at')
            ->take(10)
            ->get();

        $finished = Partido::where('status', Partido::STATUS_FINISHED)
            ->orderByDesc('played_at')
            ->take(6)
            ->get();

        $myEntries = $request->user()
            ->entries()
            ->whereIn('match_id', $upcoming->pluck('id'))
            ->get()
            ->keyBy('match_id');

        return view('dashboard', compact('upcoming', 'finished', 'myEntries'));
    }
}
