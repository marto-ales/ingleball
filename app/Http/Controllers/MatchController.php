<?php

namespace App\Http\Controllers;

use App\Models\Partido;
use App\Services\MessagingService;
use App\Services\RecurringMatchService;
use App\Services\ReminderService;
use App\Services\TeamService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MatchController extends Controller
{
    public function __construct(
        private TeamService $teams,
        private ReminderService $reminders,
        private MessagingService $messaging,
        private RecurringMatchService $recurring,
    ) {}

    public function index(): View
    {
        $this->recurring->ensureUpcoming();
        Partido::whereIn('status', [Partido::STATUS_OPEN, Partido::STATUS_LOCKED])
            ->where('played_at', '<', now())
            ->get()
            ->each(fn (Partido $match) => $match->autoFinish());

        $all = Partido::with('creator')
            ->orderByDesc('played_at')
            ->get()
            ->groupBy(fn (Partido $match): string => match (true) {
                $match->isCancelled() => 'cancelled',
                $match->isFinished() => 'finished',
                default => 'upcoming',
            });

        return view('matches.index', ['matches' => $all]);
    }

    public function create(): View
    {
        return view('matches.create');
    }

    public function edit(Partido $match): View
    {
        abort_unless($match->isActive() || $match->isCancelled(), 403);

        return view('matches.edit', ['match' => $match]);
    }

    public function update(Request $request, Partido $match): RedirectResponse
    {
        abort_unless($match->isActive() || $match->isCancelled(), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'played_at' => ['required', 'date', 'after:now'],
            'venue' => ['nullable', 'string', 'max:120'],
            'field_value' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'size' => ['required', 'integer', 'in:4,5,6'],
        ]);

        $match->update([
            'title' => $data['title'],
            'played_at' => $data['played_at'],
            'venue' => $data['venue'] ?? null,
            'field_value' => $data['field_value'] ?? null,
            'size' => $data['size'],
        ]);

        return redirect()
            ->route('matches.show', $match)
            ->with('status', 'Partido actualizado.');
    }

    public function cancel(Partido $match): RedirectResponse
    {
        abort_unless($match->isActive(), 403, 'Solo se puede cancelar un partido que no esté finalizado.');

        $match->update(['status' => Partido::STATUS_CANCELLED]);

        return back()->with('status', 'Partido cancelado.');
    }

    public function reactivate(Partido $match): RedirectResponse
    {
        abort_unless($match->isCancelled(), 403);

        $match->update(['status' => Partido::STATUS_OPEN]);

        return back()->with('status', 'Partido reactivado.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'played_at' => ['required', 'date', 'after:now'],
            'venue' => ['nullable', 'string', 'max:120'],
            'field_value' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'size' => ['required', 'integer', 'in:4,5,6'],
            'recurring' => ['nullable', 'boolean'],
        ]);

        $match = Partido::create([
            'title' => $data['title'],
            'played_at' => $data['played_at'],
            'venue' => $data['venue'] ?? null,
            'field_value' => $data['field_value'] ?? null,
            'size' => $data['size'],
            'status' => Partido::STATUS_OPEN,
            'created_by' => $request->user()->id,
            'recurring' => $request->boolean('recurring'),
        ]);

        if ($match->recurring) {
            $this->recurring->ensureUpcoming();
        }

        return redirect()
            ->route('matches.show', $match)
            ->with('status', 'Partido creado.');
    }

    public function show(Request $request, Partido $match): View
    {
        $match->autoFinish();

        $match->load([
            'entries.user', 'entries.guest',
            'guests',
            'result.mvpUser', 'result.mvpGuest',
            'teams.user', 'teams.guest',
            'goals',
        ]);

        $me = $request->user();
        $myEntry = $match->entries()->where('user_id', $me->id)->first();

        $entriesGoing = $match->entries()->where('role', 'going')->get()->values();
        $entriesSubstitute = $match->entries()->where('role', 'substitute')->get()->values();

        $teamA = $match->teams()->where('team', 'A')->get(['match_teams.*'])->load('user', 'guest');
        $teamB = $match->teams()->where('team', 'B')->get(['match_teams.*'])->load('user', 'guest');

        $goingCount = $match->entries()->where('role', 'going')->count();
        $autoSize = $this->teams->chooseTeamSize($goingCount);

        $participants = $this->participants($match);

        $waGroup = $me->is_organizer ? $me->whatsapp_group : null;

        $shareMessage = $this->messaging->message($match, $teamA, $teamB, $entriesGoing, $entriesSubstitute);

        return view('matches.show', [
            'match' => $match,
            'myEntry' => $myEntry,
            'entriesGoing' => $entriesGoing,
            'entriesSubstitute' => $entriesSubstitute,
            'teamA' => $teamA,
            'teamB' => $teamB,
            'goingCount' => $goingCount,
            'autoSize' => $autoSize,
            'participants' => $participants,
            'waGroup' => $waGroup,
            'shareMessage' => $shareMessage,
        ]);
    }

    public function toggleRecurring(Partido $match): RedirectResponse
    {
        $match->update(['recurring' => ! $match->recurring]);

        if ($match->recurring) {
            $match->update(['recurring_id' => null]);
            $this->recurring->ensureUpcoming();
        }

        return back()->with(
            'status',
            $match->recurring ? 'Partido recurrente: se abre cada semana automáticamente.' : 'Se quitó la recurrencia.'
        );
    }

    public function lock(Partido $match): RedirectResponse
    {
        $match->update(['status' => Partido::STATUS_LOCKED, 'locked_at' => now()]);

        return back()->with('status', 'Lista cerrada.');
    }

    public function unlock(Partido $match): RedirectResponse
    {
        $match->update(['status' => Partido::STATUS_OPEN, 'locked_at' => null]);

        return back()->with('status', 'Lista reabierta.');
    }

    public function remind(Partido $match): RedirectResponse
    {
        $sent = 0;

        foreach ($match->entries()->where('role', 'going')->with('user')->get() as $entry) {
            if ($entry->user !== null && $this->reminders->sendEmail($entry->user, $match)) {
                $sent++;
            }
        }

        return back()->with('status', "Se enviaron {$sent} recordatorios por email. Abrí el grupo de WhatsApp para avisar al resto.");
    }

    /**
     * Flat list of playable participants (users + guests) for the rating and
     * MVP forms.
     *
     * @return array<int, array{token: string, type: string, name: string}>
     */
    private function participants(Partido $match): array
    {
        $participants = [];

        foreach ($match->entries()->where('role', '!=', 'out')->with(['user', 'guest'])->get() as $entry) {
            if ($entry->user !== null) {
                $participants[] = ['token' => 'user:'.$entry->user->id, 'type' => 'user', 'name' => $entry->user->name];
            } elseif ($entry->guest !== null) {
                $participants[] = ['token' => 'guest:'.$entry->guest->id, 'type' => 'guest', 'name' => $entry->guest->name];
            }
        }

        return $participants;
    }
}
