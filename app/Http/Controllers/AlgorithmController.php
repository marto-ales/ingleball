<?php

namespace App\Http\Controllers;

use App\Services\AlgorithmSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AlgorithmController extends Controller
{
    public function __construct(private AlgorithmSettings $settings) {}

    public function index(): View
    {
        return view('algorithm.index', [
            'attributes' => (array) config('balance.attributes'),
            'order' => $this->settings->order(),
            'weightSequence' => array_values($this->settings->weights()),
            'randomTieBreak' => $this->settings->randomTieBreak(),
            'spreadGoalies' => $this->settings->spreadGoalies(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $attributes = (array) config('balance.attributes');

        $data = $request->validate([
            'order' => ['required', 'array', 'size:'.count($attributes)],
            'order.*' => ['required', 'string', 'in:'.implode(',', $attributes)],
            'random_tie_break' => ['nullable', 'boolean'],
            'spread_goalies' => ['nullable', 'boolean'],
        ]);

        $order = array_values($data['order']);

        if (array_diff($attributes, $order) !== []) {
            throw ValidationException::withMessages(['order' => 'Faltan atributos en el orden.']);
        }

        if (count(array_unique($order)) !== count($attributes)) {
            throw ValidationException::withMessages(['order' => 'Cada atributo debe aparecer una sola vez.']);
        }

        $this->settings->update([
            'order' => $order,
            'random_tie_break' => $request->boolean('random_tie_break'),
            'spread_goalies' => $request->boolean('spread_goalies'),
        ]);

        return back()->with('status', 'Configuración del algoritmo guardada.');
    }
}
