<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Organizers evaluate a player's whole profile (speed, skill, passing,
 * shooting, defense, goalkeeper skill). One editable evaluation per
 * (organizer, player): it never accumulates history.
 */
class PlayerEvaluationController extends Controller
{
    public function edit(Request $request, User $user): View
    {
        abort_if($user->is($request->user()), 403, 'No podés evaluarte a vos mismo.');

        $evaluation = $request->user()->evaluationsGiven()
            ->where('rated_user_id', $user->id)
            ->first();

        return view('evaluation.edit', [
            'player' => $user,
            'evaluation' => $evaluation,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'No podés evaluarte a vos mismo.');

        $data = $request->validate([
            'speed' => ['required', 'integer', 'between:0,10'],
            'skill' => ['required', 'integer', 'between:0,10'],
            'passing' => ['required', 'integer', 'between:0,10'],
            'shooting' => ['required', 'integer', 'between:0,10'],
            'defense' => ['required', 'integer', 'between:0,10'],
            'goalkeeping' => ['required', 'integer', 'between:0,10'],
        ]);

        $request->user()->evaluationsGiven()->updateOrCreate(
            ['rated_user_id' => $user->id],
            [
                'speed' => (int) $data['speed'],
                'skill' => (int) $data['skill'],
                'passing' => (int) $data['passing'],
                'shooting' => (int) $data['shooting'],
                'defense' => (int) $data['defense'],
                'goalkeeping' => (int) $data['goalkeeping'],
            ],
        );

        return redirect()->route('stats.show', $user)->with('status', 'Evaluación de '.$user->name.' guardada.');
    }
}
