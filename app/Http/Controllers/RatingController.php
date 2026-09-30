<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Group;
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
            'overall' => ['required', 'integer', 'between:-5,5'],
        ]);

        [$type, $id] = explode(':', $data['rated']);
        $id = (int) $id;
        $me = $request->user();

        // The slider goes from -5 to +5, but the stored overall stays 0-10.
        $attributes = [
            'overall' => (int) $data['overall'] + 5,
        ];

        if ($type === 'user') {
            abort_unless($id !== $me->id, 422, 'No puedes calificarte a ti mismo.');
            abort_unless(
                Group::membersQuery($match->group_id)->whereKey($id)->exists(),
                404,
                'Ese jugador no es del grupo.',
            );
            Rating::updateOrCreate(
                ['rater_user_id' => $me->id, 'rated_user_id' => $id, 'match_id' => $match->id],
                $attributes + ['group_id' => $match->group_id],
            );
        } else {
            abort_unless(
                Guest::whereKey($id)->where('group_id', $match->group_id)->exists(),
                404,
                'Ese invitado no es del grupo.',
            );
            Rating::updateOrCreate(
                ['rater_user_id' => $me->id, 'rated_guest_id' => $id, 'match_id' => $match->id],
                $attributes + ['group_id' => $match->group_id],
            );
        }

        return back()->with('status', 'Calificación guardada. ¡Gracias!');
    }
}
