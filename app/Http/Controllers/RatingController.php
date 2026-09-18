<?php

namespace App\Http\Controllers;

use App\Models\Partido;
use App\Models\Rating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RatingController extends Controller
{
    public function create(Request $request, Partido $match): View
    {
        $me = $request->user();

        $participants = $match->entries()
            ->where('role', '!=', 'out')
            ->with(['user', 'guest'])
            ->get()
            ->map(function ($entry) use ($me) {
                if ($entry->user !== null) {
                    if ($entry->user->id === $me->id) {
                        return null;
                    }

                    return ['token' => 'user:'.$entry->user->id, 'name' => $entry->user->name];
                }

                if ($entry->guest !== null) {
                    return ['token' => 'guest:'.$entry->guest->id, 'name' => $entry->guest->name];
                }

                return null;
            })
            ->filter()
            ->values();

        $existing = $me->ratingsGiven()
            ->where('match_id', $match->id)
            ->get()
            ->keyBy(fn (Rating $rating): string => $rating->rated_user_id !== null
                ? 'user:'.$rating->rated_user_id
                : 'guest:'.$rating->rated_guest_id);

        return view('rating.create', compact('match', 'participants', 'existing'));
    }

    public function store(Request $request, Partido $match): RedirectResponse
    {
        $data = $request->validate([
            'rated' => ['required', 'string', 'regex:/(user|guest):\d+/'],
            'overall' => ['required', 'integer', 'between:0,10'],
        ]);

        [$type, $id] = explode(':', $data['rated']);
        $id = (int) $id;
        $me = $request->user();

        $attributes = [
            'overall' => (int) $data['overall'],
        ];

        if ($type === 'user') {
            abort_unless($id !== $me->id, 422, 'No puedes calificarte a ti mismo.');
            Rating::updateOrCreate(
                ['rater_user_id' => $me->id, 'rated_user_id' => $id, 'match_id' => $match->id],
                $attributes,
            );
        } else {
            Rating::updateOrCreate(
                ['rater_user_id' => $me->id, 'rated_guest_id' => $id, 'match_id' => $match->id],
                $attributes,
            );
        }

        return back()->with('status', 'Calificación guardada. ¡Gracias!');
    }
}
