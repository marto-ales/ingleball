<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Partido;
use App\Models\User;
use App\Services\MessagingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EntryController extends Controller
{
    public function __construct(private MessagingService $messaging) {}

    public function update(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($match->isOpen(), 403, 'La lista ya está cerrada.');

        $data = $request->validate(['role' => ['required', Rule::in(['going', 'substitute'])]]);
        $me = $request->user();

        $current = $match->entries()
            ->where('match_id', $match->id)
            ->where('user_id', $me->id)
            ->first();

        // The going list never exceeds the match capacity: the player past the
        // last slot automatically lands on the substitutes bench.
        $role = $data['role'];

        if ($role === 'going' && $current?->role !== 'going' && ! $match->hasRoomForGoing()) {
            $role = 'substitute';
        }

        if ($current === null) {
            $match->entries()->create([
                'user_id' => $me->id,
                'role' => $role,
            ]);
        } else {
            $match->entries()
                ->where('match_id', $match->id)
                ->where('user_id', $me->id)
                ->update(['role' => $role]);
        }

        return back()
            ->with(
                'status',
                $role === 'going'
                    ? '¡Te anotaste para jugar!'
                    : ($data['role'] === 'going'
                        ? 'La lista de titulares está completa: quedaste como suplente.'
                        : 'Quedaste como suplente.')
            )
            ->with('attendance', $this->attendanceSuggestion($match, $role));
    }

    public function destroy(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($match->isOpen(), 403, 'La lista ya está cerrada.');

        $match->entries()
            ->where('match_id', $match->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        $match->promoteSubstitutes();

        return back()
            ->with('status', 'Te quitaste de la lista.')
            ->with('attendance', $this->attendanceSuggestion($match, 'out'));
    }

    public function manage(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($match->isOpen(), 403, 'La lista ya está cerrada.');

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'guest_id' => ['nullable', 'integer', 'exists:guests,id'],
            'role' => ['required', Rule::in(['going', 'substitute', 'out'])],
        ]);

        abort_unless(isset($data['user_id']) || isset($data['guest_id']), 422, 'Falta indicar el jugador.');

        if (isset($data['user_id'])) {
            $target = User::findOrFail($data['user_id']);
            abort_unless($target->isMemberOf($match->group_id), 404, 'Ese jugador no es del grupo.');
            abort_if($target->isBannedIn($match->group_id), 403, 'Ese jugador está suspendido.');
            $key = 'user_id';
        } else {
            $target = Guest::findOrFail($data['guest_id']);
            abort_unless($target->group_id === $match->group_id, 404, 'Ese invitado no es del grupo.');
            $key = 'guest_id';
        }

        $match->entries()
            ->where('match_id', $match->id)
            ->where($key, $target->id)
            ->delete();

        if ($data['role'] === 'out') {
            if ($target instanceof Guest && ! $target->entries()->exists()) {
                $target->delete();
            }

            $match->promoteSubstitutes();

            return back()->with('status', $target->name.' fue quitado de la lista.');
        }

        // 'going' past the capacity lands on the bench too.
        $role = ($data['role'] === 'going' && ! $match->hasRoomForGoing()) ? 'substitute' : $data['role'];

        $match->entries()->create([
            'match_id' => $match->id,
            $key => $target->id,
            'role' => $role,
        ]);

        return back()->with(
            'status',
            $target->name.' quedó como '.($role === 'going' ? 'titular' : 'suplente').'.'
        );
    }

    /**
     * @return array{role: string, title: string, message: string, wa_group: ?string}
     */
    private function attendanceSuggestion(Partido $match, string $role): array
    {
        $title = match ($role) {
            'substitute' => 'Quedaste como suplente',
            'out' => 'Ya no vas',
            default => '¡Te anotaste!',
        };

        $entriesGoing = $match->entries()->where('role', 'going')->get()->values();
        $entriesSubstitute = $match->entries()->where('role', 'substitute')->get()->values();
        $teamA = $match->teams()->where('team', 'A')->get(['match_teams.*'])->load('user', 'guest');
        $teamB = $match->teams()->where('team', 'B')->get(['match_teams.*'])->load('user', 'guest');

        $message = $this->messaging->message($match, $teamA, $teamB, $entriesGoing, $entriesSubstitute);

        $waGroup = $match->group?->whatsapp_group;

        return [
            'role' => $role,
            'title' => $title,
            'message' => $message,
            'wa_group' => $waGroup,
        ];
    }
}
