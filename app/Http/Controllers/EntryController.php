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

        $exists = $match->entries()
            ->where('match_id', $match->id)
            ->where('user_id', $me->id)
            ->exists();

        if (! $exists && $data['role'] === 'going') {
            $match->entries()->create([
                'user_id' => $me->id,
                'role' => 'going',
            ]);
        } else {
            $match->entries()->updateOrCreate(
                ['match_id' => $match->id, 'user_id' => $me->id],
                ['role' => $data['role']],
            );
        }

        return back()
            ->with(
                'status',
                $data['role'] === 'going' ? '¡Te anotaste para jugar!' : 'Quedaste como suplente.'
            )
            ->with('attendance', $this->attendanceSuggestion($request, $match, $data['role']));
    }

    public function destroy(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($match->isOpen(), 403, 'La lista ya está cerrada.');

        $match->entries()
            ->where('match_id', $match->id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()
            ->with('status', 'Te quitaste de la lista.')
            ->with('attendance', $this->attendanceSuggestion($request, $match, 'out'));
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
            abort_if($target->isBanned(), 403, 'Ese jugador está suspendido.');
            $key = 'user_id';
        } else {
            $target = Guest::findOrFail($data['guest_id']);
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

            return back()->with('status', $target->name.' fue quitado de la lista.');
        }

        $match->entries()->create([
            'match_id' => $match->id,
            $key => $target->id,
            'role' => $data['role'],
        ]);

        return back()->with(
            'status',
            $target->name.' quedó como '.($data['role'] === 'going' ? 'titular' : 'suplente').'.'
        );
    }

    /**
     * @return array{role: string, title: string, message: string, wa_group: ?string}
     */
    private function attendanceSuggestion(Request $request, Partido $match, string $role): array
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

        $waGroup = $request->user()->is_organizer ? $request->user()->whatsapp_group : null;

        return [
            'role' => $role,
            'title' => $title,
            'message' => $message,
            'wa_group' => $waGroup,
        ];
    }
}
