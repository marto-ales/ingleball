<?php

namespace App\Services;

use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\MatchEntry;
use App\Models\MatchResult;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Joining and leaving a group. The account is shared and stays; everything
 * that belongs to a group (profile, ratings, evaluations, entries) is created
 * and destroyed with the membership, so nothing of one group leaks into
 * another and leaving leaves no trace.
 */
class GroupMembershipService
{
    public function join(User $user, Group $group, bool $isOrganizer = false): GroupMembership
    {
        $membership = $user->membershipIn($group->id);

        if ($membership instanceof GroupMembership) {
            return $membership;
        }

        $membership = GroupMembership::create([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'is_organizer' => $isOrganizer,
        ]);

        $this->ensureProfile($user, $group->id);

        $user->unsetRelation('memberships');
        $user->unsetRelation('players');

        return $membership;
    }

    /**
     * A player profile of this account in this group, created with neutral
     * values when it does not exist yet.
     */
    public function ensureProfile(User $user, int $groupId): Player
    {
        return Player::firstOrCreate(
            ['user_id' => $user->id, 'group_id' => $groupId],
            [
                'speed' => 5, 'skill' => 5, 'passing' => 5, 'shooting' => 5, 'defense' => 5, 'goalkeeping' => 5,
            ]
        );
    }

    /**
     * Remove the account from a group together with everything it did there.
     * An organizer cannot leave the group they run.
     *
     * @throws RuntimeException
     */
    public function leave(User $user, Group $group): void
    {
        $membership = $user->membershipIn($group->id);

        if (! $membership instanceof GroupMembership) {
            return;
        }

        if ($membership->is_organizer) {
            throw new RuntimeException('Un organizador no puede salir del grupo que organiza.');
        }

        DB::transaction(function () use ($user, $group): void {
            $groupId = $group->id;
            $matchIds = DB::table('matches')->where('group_id', $groupId)->pluck('id')->all();

            DB::table('ratings')
                ->where('group_id', $groupId)
                ->where(fn ($query) => $query->where('rater_user_id', $user->id)->orWhere('rated_user_id', $user->id))
                ->delete();

            DB::table('player_evaluations')
                ->where('group_id', $groupId)
                ->where(fn ($query) => $query->where('organizer_user_id', $user->id)->orWhere('rated_user_id', $user->id))
                ->delete();

            MatchEntry::where('user_id', $user->id)
                ->whereIn('match_id', $matchIds)
                ->delete();

            MatchResult::where('mvp_user_id', $user->id)
                ->whereIn('match_id', $matchIds)
                ->update(['mvp_user_id' => null]);

            DB::table('players')->where('user_id', $user->id)->where('group_id', $groupId)->delete();

            DB::table('group_user')->where('user_id', $user->id)->where('group_id', $groupId)->delete();
        });

        $user->unsetRelation('memberships');
        $user->unsetRelation('players');
        $user->unsetRelation('groups');
    }
}
