<?php

namespace App\Http\Controllers;

use App\Models\Partido;
use App\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function __construct(private TeamService $service) {}

    public function generate(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($match->isLocked(), 403, 'La lista debe estar cerrada para armar los equipos.');

        $preferred = $request->integer('size') ?: null;
        $this->service->generate($match, $preferred);

        return back()->with('status', 'Equipos generados.');
    }

    public function swap(Request $request, Partido $match): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'integer'],
            'to' => ['required', 'integer'],
        ]);

        $from = $match->teams()->whereKey($data['from'])->first();
        $to = $match->teams()->whereKey($data['to'])->first();

        abort_unless($from !== null && $to !== null && $from->team !== $to->team, 422, 'Selección inválida.');

        [$from->team, $to->team] = [$to->team, $from->team];
        [$from->position, $to->position] = [$to->position, $from->position];
        $from->save();
        $to->save();

        return back()->with('status', 'Jugadores intercambiados.');
    }
}
