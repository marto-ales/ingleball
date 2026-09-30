<?php

namespace App\Http\Middleware;

use App\Models\GroupMembership;
use App\Support\ActiveGroup;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the group the person is working in and hands it to every view, so
 * a page never has to guess which group it is about. Without a group there is
 * nothing to show: the person lands on "Mi grupo" to join one with a code.
 */
class EnsureActiveGroup
{
    public function __construct(private ActiveGroup $activeGroup) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $this->activeGroup->user();
        $group = $this->activeGroup->group();

        if ($user !== null) {
            $user->loadMissing('memberships.group');

            $memberships = $user->memberships
                ->reject(fn (GroupMembership $membership) => $membership->isBanned())
                ->sortBy(fn (GroupMembership $membership) => $membership->group->name)
                ->values();

            View::share('myGroups', $memberships);
        } else {
            View::share('myGroups', collect());
        }

        View::share('activeGroup', $group);
        View::share('isOrganizer', $this->activeGroup->isOrganizer());

        // "Mi grupo" is where a person goes when they have none, so it is the
        // one page that still renders without a group in use.
        if ($group === null && ! $request->routeIs('groups.show')) {
            return redirect()
                ->route('groups.show')
                ->with('status', $user?->memberships->isEmpty()
                    ? 'Todavía no pertenecés a ningún grupo. Pedile el código a quien lo organiza.'
                    : 'No tenés ningún grupo disponible.');
        }

        return $next($request);
    }
}
