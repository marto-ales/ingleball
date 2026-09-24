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
            'selfWeight' => $this->settings->selfWeight(),
            'weightByForm' => $this->settings->weightByForm(),
            'formSpan' => $this->settings->formSpan(),
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
            'self_weight' => ['required', 'numeric', 'between:0,1'],
            'weight_by_form' => ['nullable', 'boolean'],
            'form_span' => ['nullable', 'numeric', 'between:0.05,0.5'],
        ]);

        $order = array_values($data['order']);

        if (array_diff($attributes, $order) !== []) {
            throw ValidationException::withMessages(['order' => 'Faltan atributos en el orden.']);
        }

        if (count(array_unique($order)) !== count($attributes)) {
            throw ValidationException::withMessages(['order' => 'Cada atributo debe aparecer una sola vez.']);
        }

        $weightByForm = $request->boolean('weight_by_form');

        // The form span only applies while the recent-form adjustment is on.
        $formSpan = $weightByForm
            ? (float) ($data['form_span'] ?? config('balance.form_span', 0.2))
            : $this->settings->formSpan();

        $this->settings->update([
            'order' => $order,
            'random_tie_break' => $request->boolean('random_tie_break'),
            'spread_goalies' => $request->boolean('spread_goalies'),
            'self_weight' => $data['self_weight'],
            'weight_by_form' => $weightByForm,
            'form_span' => $formSpan,
        ]);

        return back()->with('status', 'Configuración del algoritmo guardada.');
    }
}
