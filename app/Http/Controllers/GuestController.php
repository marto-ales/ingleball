<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Partido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    public function store(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($match->isOpen(), 403, 'La lista ya está cerrada.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:20'],
            'speed' => ['nullable', 'integer', 'between:1,10'],
            'skill' => ['nullable', 'integer', 'between:1,10'],
            'passing' => ['nullable', 'integer', 'between:1,10'],
            'shooting' => ['nullable', 'integer', 'between:1,10'],
            'defense' => ['nullable', 'integer', 'between:1,10'],
            'overall' => ['nullable', 'integer', 'between:1,10'],
        ]);

        $guest = $match->guests()->create($data);

        $nextOrder = ((int) $match->entries()->max('list_order')) + 1;
        $match->entries()->create([
            'guest_id' => $guest->id,
            'role' => 'going',
            'list_order' => $nextOrder,
        ]);

        return back()->with('status', 'Invitado a ' . $data['name'] . '.');
    }

    public function destroy(Partido $match, Guest $guest): RedirectResponse
    {
        abort_unless($match->isOpen(), 403, 'La lista ya está cerrada.');
        abort_unless($guest->match_id === $match->id, 404);

        $match->entries()->where('guest_id', $guest->id)->delete();
        $guest->delete();

        return back()->with('status', 'Invitado retirado.');
    }
}
