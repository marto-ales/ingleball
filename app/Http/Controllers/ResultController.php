<?php

namespace App\Http\Controllers;

use App\Models\Partido;
use App\Services\MvpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResultController extends Controller
{
    public function __construct(private MvpService $mvp) {}

    public function store(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($match->isFinished(), 403, 'El resultado se puede cargar recién cuando el partido está finalizado.');
        abort_unless($match->played_at->isPast(), 403, 'El resultado se puede cargar recién pasada la hora de inicio del partido.');

        $data = $request->validate([
            'winner' => ['required', 'string', 'in:A,B,tie'],
            'diff' => ['nullable', 'integer', 'between:0,20'],
        ]);

        $match->result()->updateOrCreate(
            ['match_id' => $match->id],
            [
                'winner' => $data['winner'] === 'tie' ? null : $data['winner'],
                'diff' => (int) ($data['diff'] ?? 0),
            ],
        );

        $match->update(['status' => Partido::STATUS_FINISHED]);

        // The MVP is not picked here: it comes from the attendees' votes.
        $this->mvp->syncResult($match);

        return back()->with('status', 'Resultado registrado.');
    }
}
