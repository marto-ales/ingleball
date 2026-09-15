<?php

namespace App\Http\Controllers;

use App\Models\Partido;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function store(Request $request, Partido $match): RedirectResponse
    {
        $data = $request->validate([
            'winner' => ['required', 'string', 'in:A,B,tie'],
            'diff' => ['nullable', 'integer', 'between:0,20'],
            'mvp' => ['nullable', 'string', 'regex:/(user|guest):\d+/'],
            'goals' => ['nullable', 'array'],
            'goals.*.scorer' => ['nullable', 'string', 'regex:/(user|guest):\d+/'],
            'goals.*.assister' => ['nullable', 'string', 'regex:/(user|guest):\d+/'],
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

        if (isset($data['goals'])) {
            $match->goals()->delete();

            foreach ($data['goals'] as $goal) {
                $scorerUser = $this->userId($goal['scorer'] ?? null);
                $scorerGuest = $this->guestId($goal['scorer'] ?? null);

                if ($scorerUser === null && $scorerGuest === null) {
                    continue;
                }

                $match->goals()->create([
                    'scorer_user_id' => $scorerUser,
                    'scorer_guest_id' => $scorerGuest,
                    'assister_user_id' => $this->userId($goal['assister'] ?? null),
                    'assister_guest_id' => $this->guestId($goal['assister'] ?? null),
                ]);
            }
        }

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
