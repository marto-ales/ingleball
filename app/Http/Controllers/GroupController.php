<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GroupController extends Controller
{
    /**
     * "Mi grupo" screen: name, join code and WhatsApp link of the caller's group.
     */
    public function show(Request $request): View
    {
        $group = $request->user()->group;

        return view('groups.show', [
            'group' => $group,
            'memberCount' => $group?->users()->count() ?? 0,
            'matchCount' => $group?->matches()->count() ?? 0,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $group = $this->ownGroup($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'whatsapp_group' => ['nullable', 'string', 'max:255'],
        ]);

        $group->update([
            'name' => $data['name'],
            'whatsapp_group' => $data['whatsapp_group'] ?? null,
        ]);

        return back()->with('status', 'Grupo actualizado.');
    }

    public function rotateCode(Request $request): RedirectResponse
    {
        $group = $this->ownGroup($request);

        $group->update(['join_code' => Group::randomJoinCode()]);

        return back()->with('status', 'Código de invitación renovado. El anterior ya no sirve.');
    }

    /**
     * An organizer can start another group and move into it; the players they
     * invite join with the new code.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_organizer, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $group = Group::create([
            'name' => $data['name'],
            'join_code' => Group::randomJoinCode(),
        ]);

        $request->user()->moveToGroup($group);

        return redirect()
            ->route('groups.show')
            ->with('status', 'Creaste el grupo '.$group->name.'. Compartí el código para invitar.');
    }

    private function ownGroup(Request $request): Group
    {
        $group = $request->user()->group;

        abort_if($group === null, 404, 'No tenés un grupo asignado.');

        return $group;
    }
}
