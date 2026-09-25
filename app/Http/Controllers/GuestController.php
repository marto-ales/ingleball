<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Partido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuestController extends Controller
{
    public function store(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($match->isOpen(), 403, 'La lista ya está cerrada.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:20'],
            'speed' => ['nullable', 'integer', 'between:0,10'],
            'skill' => ['nullable', 'integer', 'between:0,10'],
            'passing' => ['nullable', 'integer', 'between:0,10'],
            'shooting' => ['nullable', 'integer', 'between:0,10'],
            'defense' => ['nullable', 'integer', 'between:0,10'],
            'overall' => ['nullable', 'integer', 'between:0,10'],
            'role' => ['nullable', Rule::in(['going', 'substitute'])],
        ]);

        $guestData = $data;
        unset($guestData['role']);
        $guest = $this->findOrGlobal($guestData);
        if ($match->entries()->where('guest_id', $guest->id)->exists()) {
            return back()->with('status', $guest->name.' ya está en la lista.');
        }

        // 'going' past the capacity lands on the bench too.
        $role = $data['role'] ?? 'going';
        if ($role === 'going' && ! $match->hasRoomForGoing()) {
            $role = 'substitute';
        }

        $match->entries()->create([
            'guest_id' => $guest->id,
            'role' => $role,
        ]);

        return back()->with(
            'status',
            $role === 'substitute'
                ? $guest->name.' quedó como suplente.'
                : 'Invitado a '.$guest->name.'.'
        );
    }

    public function destroy(Partido $match, Guest $guest): RedirectResponse
    {
        abort_unless($match->isOpen(), 403, 'La lista ya está cerrada.');

        $entry = $match->entries()->where('guest_id', $guest->id)->first();
        abort_unless($entry, 404);

        $entry->delete();
        if (! $guest->entries()->exists()) {
            $guest->delete();
        }

        $match->promoteSubstitutes();

        return back()->with('status', 'Invitado retirado.');
    }

    private function findOrGlobal(array $data): Guest
    {
        $phone = ! empty($data['phone']) ? preg_replace('/[^0-9]/', '', $data['phone']) : null;

        $existing = $phone
            ? Guest::whereNotNull('phone')->get()->first(fn (Guest $g) => preg_replace('/[^0-9]/', '', (string) $g->phone) === $phone)
            : null;

        if ($existing) {
            $existing->update($data);

            return $existing;
        }

        return Guest::create($data);
    }
}
