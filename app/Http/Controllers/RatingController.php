<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Guest;
use App\Models\MatchEntry;
use App\Models\Partido;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RatingController extends Controller
{
    public function create(Request $request, Partido $match): View
    {
        $me = $request->user();

        abort_unless($this->attended($match, $me), 403, 'Solo quienes jugaron el partido pueden calificar.');

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

        return view('rating.create', compact('match', 'participants', 'existing', 'me'));
    }

    public function store(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($this->attended($match, $request->user()), 403, 'Solo quienes jugaron el partido pueden calificar.');

        $data = $request->validate([
            'ratings' => ['required', 'array'],
            'ratings.*' => ['required', 'integer', 'between:-5,5'],
        ]);

        $me = $request->user();

        foreach ($data['ratings'] as $token => $overall) {
            abort_unless((bool) preg_match('/^(user|guest):\d+$/', (string) $token), 422, 'Jugador inválido.');

            [$type, $id] = explode(':', $token);
            $id = (int) $id;

            // The slider goes from -5 to +5, but the stored overall stays 0-10.
            $attributes = [
                'overall' => (int) $overall + 5,
            ];

            if ($type === 'user') {
                abort_if($id === $me->id, 422, 'No puedes calificarte a ti mismo.');
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
        }

        return back()->with('status', 'Calificaciones guardadas. ¡Gracias!');
    }

    private function attended(Partido $match, User $user): bool
    {
        return $match->entries()
            ->where('user_id', $user->id)
            ->where('role', MatchEntry::ROLE_GOING)
            ->exists();
    }
}
