<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use App\Services\GroupMembershipService;
use App\Support\ActiveGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/**
 * Management of the members of the group in use. The account is shared across
 * groups, so everything editable here (organizer, block, membership) belongs
 * to that membership and leaves the other groups untouched.
 */
class UserManagementController extends Controller
{
    public function __construct(
        private ActiveGroup $activeGroup,
        private GroupMembershipService $memberships,
    ) {}

    public function index(): View
    {
        $groupId = $this->activeGroup->id();

        $users = Group::membersQuery($groupId)
            ->with(['players' => fn ($query) => $query->where('group_id', $groupId)])
            ->withCount(['evaluationsReceived' => fn ($query) => $query->where('group_id', $groupId)])
            ->orderBy('name')
            ->get();

        $myEvaluated = auth()->user()->evaluationsGiven()
            ->where('group_id', $groupId)
            ->pluck('rated_user_id')
            ->all();

        return view('users.index', [
            'users' => $users,
            'myEvaluated' => $myEvaluated,
        ]);
    }

    public function create(): View
    {
        return view('users.create');
    }

    /**
     * A managed player: an account stub the organizer creates so the person
     * can be rated before they ever register. It belongs to this group only.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:120'],
            'goalkeeping' => ['nullable', 'integer', 'between:0,10'],
        ]);

        $group = $this->activeGroup->group();
        $groupId = $group->id;

        $user = User::create([
            'name' => $data['name'],
            'username' => $this->uniqueUsername($data['name']),
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'is_managed' => true,
            'password' => Str::random(32),
        ]);

        $this->memberships->join($user, $group);

        if ($data['goalkeeping'] ?? null) {
            $this->memberships->ensureProfile($user, $groupId)
                ->update(['goalkeeping' => (int) $data['goalkeeping']]);
        }

        return redirect()->route('users.manage.index')
            ->with('status', 'Jugador '.$user->name.' creado ('.$user->username.'). Si se registra, recupera su historial.');
    }

    public function edit(User $user): View
    {
        $groupId = $this->activeGroup->id();

        abort_unless($user->isMemberOf($groupId), 404);

        $user->loadMissing('players');

        return view('users.edit', [
            'user' => $user,
            'membership' => $user->membershipIn($groupId),
            'profile' => $user->playerFor($groupId),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 403, 'No podés editar tu propia cuenta desde aquí; usá tu perfil.');

        $groupId = $this->activeGroup->id();
        $membership = $user->memberships()->where('group_id', $groupId)->first();

        abort_if($membership === null, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['sometimes', 'nullable', 'email', 'max:120'],
            'is_organizer' => ['nullable', 'boolean'],
            'goalkeeping' => ['nullable', 'integer', 'between:0,10'],
        ]);

        $user->forceFill([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
        ])->save();

        // Organizing is per group: this does not change what they may do in
        // the groups they share with other people.
        $membership->update(['is_organizer' => $request->boolean('is_organizer')]);

        if (! empty($data['email'])) {
            $owner = User::where('email', $data['email'])->where('id', '!=', $user->id)->first();
            if ($owner) {
                throw ValidationException::withMessages(['email' => 'Ese correo ya le pertenece a otro jugador.']);
            }

            $user->forceFill(['email' => $data['email']])->save();
        }

        if (! empty($data['goalkeeping'])) {
            $this->memberships->ensureProfile($user, $groupId)
                ->update(['goalkeeping' => (int) $data['goalkeeping']]);
        }

        return back()->with('status', 'Datos de '.$user->name.' actualizados.');
    }

    /**
     * Blocking belongs to the membership: it keeps the person out of this
     * group without touching the account or the other groups.
     */
    public function block(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 403, 'No podés bloquearte a vos mismo.');

        $membership = $this->membershipOf($user);

        $membership->update(['banned_at' => now()]);

        return back()->with('status', $user->name.' bloqueado en este grupo.');
    }

    public function unblock(User $user): RedirectResponse
    {
        $this->membershipOf($user)->update(['banned_at' => null]);

        return back()->with('status', $user->name.' desbloqueado en este grupo.');
    }

    /**
     * Taking a member out of the group. The account and its login survive: if
     * the same person plays in another group, nothing there is touched.
     */
    public function expel(User $user): RedirectResponse
    {
        abort_if($user->is(auth()->user()), 403, 'No podés sacarte a vos mismo.');

        $name = $user->name;
        $group = $this->activeGroup->group();

        abort_if($group === null, 404);

        try {
            $this->memberships->leave($user, $group);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('status', $name.' ya no pertenece a '.$group->name.'.');
    }

    private function membershipOf(User $user): GroupMembership
    {
        $membership = $user->memberships()->where('group_id', $this->activeGroup->id())->first();

        abort_if($membership === null, 404);

        return $membership;
    }

    private function uniqueUsername(string $name): string
    {
        $base = Str::lower(Str::slug($name, '_'));

        if ($base === '') {
            $base = 'jugador';
        }

        $candidate = $base;
        $suffix = 1;

        while (User::where('username', $candidate)->exists()) {
            $candidate = $base.'_'.$suffix++;
        }

        return $candidate;
    }
}
