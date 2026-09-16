<?php

namespace App\Http\Controllers;

use App\Models\Partido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EntryController extends Controller
{
    public function update(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($match->isOpen(), 403, 'La lista ya está cerrada.');

        $data = $request->validate(['role' => ['required', Rule::in(['going', 'substitute'])]]);
        $me = $request->user();

        $exists = $match->entries()
            ->where('match_id', $match->id)
            ->where('user_id', $me->id)
            ->exists();

        if (! $exists && $data['role'] === 'going') {
            $match->entries()->create([
                'user_id' => $me->id,
                'role' => 'going',
            ]);
        } else {
            $match->entries()->updateOrCreate(
                ['match_id' => $match->id, 'user_id' => $me->id],
                ['role' => $data['role']],
            );
        }

        return back()->with(
            'status',
            $data['role'] === 'going' ? '¡Te anotaste para jugar!' : 'Quedaste como suplente.'
        );
    }

    public function destroy(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($match->isOpen(), 403, 'La lista ya está cerrada.');

        $match->entries()
            ->where('match_id', $match->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()->with('status', 'Te quitaste de la lista.');
    }
}
