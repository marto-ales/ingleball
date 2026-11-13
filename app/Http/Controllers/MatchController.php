<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Partido;
use App\Services\MessagingService;
use App\Services\MvpService;
use App\Services\RecurringMatchService;
use App\Services\ReminderService;
use App\Services\TeamService;
use App\Support\ActiveGroup;
use Illuminate\Database\Eloquent\Builder;
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
        private MvpService $mvp,
        private ActiveGroup $activeGroup,
    ) {}

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
            'group_id' => $this->activeGroup->id(),
            'recurring' => $request->boolean('recurring'),
        ]);

        if ($match->recurring) {
            $this->recurring->ensureUpcoming($match->group_id);
        }

        return redirect()
            ->route('matches.show', $match)
            ->with('status', 'Partido creado.');
    }

    public function show(Request $request, Partido $match): View
    {
        $match->autoFinish();

        $match->load([
            'group',
            'entries.user', 'entries.guest',
            'guests',
            'result.mvpUser', 'result.mvpGuest',
            'teams.user', 'teams.guest',
        ]);

        $me = $request->user();
        $myEntry = $match->entries()->where('user_id', $me->id)->first();

        $entriesGoing = $match->entries()->where('role', 'going')->get()->values();
        $entriesSubstitute = $match->entries()->where('role', 'substitute')->get()->values();

        $teamA = $match->teams()->where('team', 'A')->orderBy('position')->get(['match_teams.*'])->load('user', 'guest');
        $teamB = $match->teams()->where('team', 'B')->orderBy('position')->get(['match_teams.*'])->load('user', 'guest');

        // Re-list the stored teams with the order in force now, so the rows
        // compare the right characteristic even if the lineup was saved with a
        // different one. Membership is untouched.
        $arranged = $this->teams->arrangeRows($teamA, $teamB, $match);
        $teamA = $arranged['teamA'];
        $teamB = $arranged['teamB'];

        $goingCount = $match->entries()->where('role', 'going')->count();
        $autoSize = $this->teams->chooseTeamSize($goingCount);

        $availableUsers = Group::membersQuery($match->group_id)
            ->whereDoesntHave('memberships', fn (Builder $query) => $query
                ->where('group_id', $match->group_id)
                ->whereNotNull('banned_at'))
            ->whereNotIn('id', $match->entries()->whereNotNull('user_id')->pluck('user_id'))
            ->orderBy('name')
            ->get();

        $waGroup = $match->group?->whatsapp_group;

        $shareMessage = $this->messaging->message($match, $teamA, $teamB, $entriesGoing, $entriesSubstitute);

        $mvpNames = collect($this->mvp->candidates($match))->pluck('name', 'token');
        $mvpTally = [];

        foreach ($this->mvp->tally($match) as $token => $votes) {
            $mvpTally[] = ['token' => $token, 'name' => $mvpNames[$token] ?? '?', 'votes' => $votes];
        }

        $mvpLeaders = $this->mvp->leaders($match);
        $mvpTieOptions = count($mvpLeaders) > 1
            && $match->result !== null
            && $match->result->mvp_user_id === null
            && $match->result->mvp_guest_id === null
            ? array_map(
                fn (string $token): array => ['token' => $token, 'name' => $mvpNames[$token] ?? '?'],
                $mvpLeaders,
            )
            : [];

        return view('matches.show', [
            'match' => $match,
            'myEntry' => $myEntry,
            'entriesGoing' => $entriesGoing,
            'entriesSubstitute' => $entriesSubstitute,
            'teamA' => $teamA,
            'teamB' => $teamB,
            'goingCount' => $goingCount,
            'autoSize' => $autoSize,
            'availableUsers' => $availableUsers,
            'waGroup' => $waGroup,
            'shareMessage' => $shareMessage,
            'mvpTally' => $mvpTally,
            'mvpTieOptions' => $mvpTieOptions,
        ]);
    }

    public function toggleRecurring(Partido $match): RedirectResponse
    {
        $match->update(['recurring' => ! $match->recurring]);

        if ($match->recurring) {
            $match->update(['recurring_id' => null]);
            $this->recurring->ensureUpcoming($match->group_id);
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
        $match->teams()->delete();

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
}
