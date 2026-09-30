<?php

namespace App\Support;

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * The group the person is working in right now. One account can belong to
 * several groups, so every screen needs to know which one it is about: the
 * choice lives in the session, is validated against the memberships and falls
 * back to the first available group.
 */
class ActiveGroup
{
    public const SESSION_KEY = 'active_group_id';

    private ?Group $group = null;

    /**
     * Who this answer belongs to: false until resolved, then the id of the
     * account it was resolved for, so a change of account resolves again
     * instead of handing over the previous one's group.
     */
    private int|null|false $resolvedFor = false;

    public function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public function id(): ?int
    {
        return $this->group()?->id;
    }

    public function group(): ?Group
    {
        $userId = $this->user()?->id;

        if ($this->resolvedFor !== $userId) {
            $this->group = $this->resolve();
            $this->resolvedFor = $userId;
        }

        return $this->group;
    }

    public function isOrganizer(): bool
    {
        $user = $this->user();
        $group = $this->group();

        return $user !== null && $group !== null && $user->isOrganizerIn($group->id);
    }

    /**
     * Point the session at another group of the same account. Refused for a
     * group they are not a member of, and for one they are blocked in.
     */
    public function activate(Group $group): bool
    {
        $user = $this->user();

        if ($user === null || $user->isBannedIn($group->id) || ! $user->isMemberOf($group->id)) {
            return false;
        }

        Session::put(self::SESSION_KEY, $group->id);
        $this->group = $group;
        $this->resolvedFor = $user->id;

        return true;
    }

    public function forget(): void
    {
        Session::forget(self::SESSION_KEY);
        $this->group = null;
        $this->resolvedFor = false;
    }

    /**
     * Drop the cached answer so the next call resolves again from the session.
     * Used when the session changes without going through activate(), mostly
     * by tests. The session key itself is kept.
     */
    public function invalidate(): void
    {
        $this->group = null;
        $this->resolvedFor = false;
    }

    private function resolve(): ?Group
    {
        $user = $this->user();

        if ($user === null) {
            return null;
        }

        $user->loadMissing('memberships.group');

        $memberships = $user->memberships
            ->reject(fn (GroupMembership $membership) => $membership->isBanned())
            ->sortBy(fn (GroupMembership $membership) => $membership->group->name)
            ->values();

        $stored = Session::get(self::SESSION_KEY);

        $active = $memberships->firstWhere('group_id', $stored) ?? $memberships->first();

        if ($active instanceof GroupMembership) {
            return $active->group;
        }

        return null;
    }
}
