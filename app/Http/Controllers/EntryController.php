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
            $nextOrder = ((int) $match->entries()->max('list_order')) + 1;
            $match->entries()->create([
                'user_id' => $me->id,
                'role' => 'going',
                'list_order' => $nextOrder,
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

    public function reorder(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($match->isOpen() || $match->isLocked(), 403, 'No disponible.');

        $orders = $request->validate([
            'orders' => ['nullable', 'array'],
            'orders.*' => ['integer', 'min:0'],
        ]);

        foreach ($orders['orders'] ?? [] as $entryId => $order) {
            $match->entries()->whereKey($entryId)->update(['list_order' => (int) $order]);
        }

        return back()->with('status', 'Orden actualizado.');
    }
}
