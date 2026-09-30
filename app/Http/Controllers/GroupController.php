<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Services\GroupMembershipService;
use App\Support\ActiveGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class GroupController extends Controller
{
    public function __construct(
        private ActiveGroup $activeGroup,
        private GroupMembershipService $memberships,
    ) {}

    /**
     * "Mi grupo" screen: the group in use, the code that lets others join it
     * and the list of the groups this account belongs to.
     */
    public function show(): View
    {
        $group = $this->activeGroup->group();
        $user = request()->user();

        return view('groups.show', [
            'group' => $group,
            'memberCount' => $group?->memberCount() ?? 0,
            'matchCount' => $group?->matches()->count() ?? 0,
            'membership' => $group !== null ? $user->membershipIn($group->id) : null,
        ]);
    }

    /**
     * Work in another group of the same account. Everything on screen keeps
     * pointing at the group chosen here.
     */
    public function activate(Request $request): RedirectResponse
    {
        $data = $request->validate(['group' => ['required', 'integer']]);

        abort_unless(
            $request->user()->isMemberOf((int) $data['group']) && ! $request->user()->isBannedIn((int) $data['group']),
            403,
            'No pertenecés a ese grupo.'
        );

        $this->activeGroup->activate(Group::findOrFail($data['group']));

        return redirect()->route('dashboard')->with('status', 'Ahora estás en '.$this->activeGroup->group()->name.'.');
    }

    public function update(Request $request): RedirectResponse
    {
        $group = $this->activeGroup->group();

        abort_if($group === null, 404, 'No tenés un grupo asignado.');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'whatsapp_group' => ['nullable', 'string', 'max:255'],
        ]);

        $group->update([
            'name' => $data['name'],
            'whatsapp_group' => ($data['whatsapp_group'] ?? null) ?: null,
        ]);

        return back()->with('status', 'Grupo actualizado.');
    }

    public function rotateCode(Request $request): RedirectResponse
    {
        $group = $this->activeGroup->group();

        abort_if($group === null, 404, 'No tenés un grupo asignado.');

        $group->update(['join_code' => Group::randomJoinCode()]);

        return back()->with('status', 'Código de invitación renovado. El anterior ya no sirve.');
    }

    /**
     * One account, several groups: joining with a code adds the group and the
     * player profile that goes with it, and leaves the account alone.
     */
    public function join(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'group_code' => ['required', 'string', 'exists:groups,join_code'],
        ]);

        $group = Group::where('join_code', $data['group_code'])->firstOrFail();
        $user = $request->user();

        if ($user->isMemberOf($group->id)) {
            $this->activeGroup->activate($group);

            return back()->with('status', 'Ya pertenecías a '.$group->name.'.');
        }

        $this->memberships->join($user, $group);
        $this->activeGroup->activate($group);

        return back()->with('status', 'Te sumaste a '.$group->name.'.');
    }

    /**
     * Leaving drops the profile, the ratings, the evaluations and the entries
     * of that group. The account, its login and its other groups stay.
     */
    public function leave(Request $request): RedirectResponse
    {
        $group = $this->activeGroup->group();

        abort_if($group === null, 404, 'No tenés un grupo asignado.');

        $name = $group->name;

        try {
            $this->memberships->leave($request->user(), $group);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $this->activeGroup->forget();

        return back()->with('status', 'Saliste de '.$name.'.');
    }
}
