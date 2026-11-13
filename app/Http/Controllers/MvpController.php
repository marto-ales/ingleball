<?php

namespace App\Http\Controllers;

use App\Models\Partido;
use App\Services\MvpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MvpController extends Controller
{
    public function __construct(private MvpService $mvp) {}

    /**
     * An attendee votes for (or changes their vote for) the match MVP.
     */
    public function store(Request $request, Partido $match): RedirectResponse
    {
        $me = $request->user();

        abort_unless($this->mvp->canVote($match, $me), 403, 'El MVP se vota cuando el resultado está cargado y solo quienes jugaron pueden votar.');

        $token = $this->validatedToken($request);

        abort_if($token === 'user:'.$me->id, 422, 'No puedes votarte a ti mismo.');

        $candidate = $this->mvp->candidateFor($match, $token);

        abort_if($candidate === null, 422, 'Ese jugador no jugó este partido.');

        $this->mvp->vote($match, $me, $candidate);

        return back()->with('status', 'Voto de MVP guardado. ¡Gracias!');
    }

    /**
     * The organizer resolves a tie at the top of the vote.
     */
    public function breakTie(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($match->result()->exists(), 422, 'Primero registrá el resultado del partido.');

        $token = $this->validatedToken($request);

        abort_unless(
            in_array($token, $this->mvp->leaders($match), true),
            422,
            'Ese jugador no está empatado en la votación.',
        );

        $this->mvp->breakTie($match, $token);

        return back()->with('status', 'MVP definido.');
    }

    private function validatedToken(Request $request): string
    {
        $data = $request->validate([
            'mvp' => ['required', 'string', 'regex:/^(user|guest):\d+$/'],
        ]);

        return $data['mvp'];
    }
}
