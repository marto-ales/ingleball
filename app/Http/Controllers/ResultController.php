<?php

namespace App\Http\Controllers;

use App\Models\Partido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function store(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($match->isFinished(), 403, 'El resultado se puede cargar recién cuando el partido está finalizado.');
        abort_unless($match->played_at->isPast(), 403, 'El resultado se puede cargar recién pasada la hora de inicio del partido.');

        $data = $request->validate([
            'winner' => ['required', 'string', 'in:A,B,tie'],
            'diff' => ['nullable', 'integer', 'between:0,20'],
            'mvp' => ['nullable', 'string', 'regex:/(user|guest):\d+/'],
        ]);

        $match->result()->updateOrCreate(
            ['match_id' => $match->id],
            [
                'winner' => $data['winner'] === 'tie' ? null : $data['winner'],
                'diff' => (int) ($data['diff'] ?? 0),
                'mvp_user_id' => $this->userId($data['mvp'] ?? null),
                'mvp_guest_id' => $this->guestId($data['mvp'] ?? null),
            ],
        );

        $match->update(['status' => Partido::STATUS_FINISHED]);

        return back()->with('status', 'Resultado registrado.');
    }

    private function userId(?string $token): ?int
    {
        if ($token !== null && str_starts_with($token, 'user:')) {
            return (int) substr($token, 5);
        }

        return null;
    }

    private function guestId(?string $token): ?int
    {
        if ($token !== null && str_starts_with($token, 'guest:')) {
            return (int) substr($token, 6);
        }

        return null;
    }
}
