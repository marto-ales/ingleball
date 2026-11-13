<?php

namespace App\Services;

use App\Models\MatchEntry;
use App\Models\MvpVote;
use App\Models\Partido;
use App\Models\User;

/**
 * MVP chosen by the players who attended: every starter casts one vote for
 * another starter, the most voted wins, and only a tie at the top needs the
 * organizer to step in. The resolved pick is kept in the match result so the
 * statistics keep counting it the same way.
 */
class MvpService
{
    /**
     * Attendance is the going list, same rule the per-match ratings use.
     */
    public function attended(Partido $match, User $user): bool
    {
        return $match->entries()
            ->where('user_id', $user->id)
            ->where('role', MatchEntry::ROLE_GOING)
            ->exists();
    }

    /**
     * Starters eligible for the vote, as tokens "user:ID" / "guest:ID".
     *
     * @return array<int, array{token: string, type: string, id: int, name: string}>
     */
    public function candidates(Partido $match): array
    {
        $candidates = [];

        foreach ($match->entries()->where('role', MatchEntry::ROLE_GOING)->with(['user', 'guest'])->get() as $entry) {
            if ($entry->user !== null) {
                $candidates[] = [
                    'token' => 'user:'.$entry->user->id,
                    'type' => 'user',
                    'id' => $entry->user->id,
                    'name' => $entry->user->name,
                ];
            } elseif ($entry->guest !== null) {
                $candidates[] = [
                    'token' => 'guest:'.$entry->guest->id,
                    'type' => 'guest',
                    'id' => $entry->guest->id,
                    'name' => $entry->guest->name,
                ];
            }
        }

        // The going list should not repeat a player, but if it ever does the
        // vote must still show a single slot per person.
        $unique = [];

        foreach ($candidates as $candidate) {
            $unique[$candidate['token']] ??= $candidate;
        }

        return array_values($unique);
    }

    /**
     * Candidates a given attendee may vote for: everyone who played except
     * themselves.
     *
     * @return array<int, array{token: string, type: string, id: int, name: string}>
     */
    public function candidatesExcept(Partido $match, User $user): array
    {
        $own = 'user:'.$user->id;

        return array_values(array_filter(
            $this->candidates($match),
            fn (array $candidate): bool => $candidate['token'] !== $own,
        ));
    }

    /**
     * @return array{token: string, type: string, id: int, name: string}|null
     */
    public function candidateFor(Partido $match, string $token): ?array
    {
        foreach ($this->candidates($match) as $candidate) {
            if ($candidate['token'] === $token) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Voting opens once the organizer has recorded the result, and only for
     * the players who signed up as going. In practice people rate and vote
     * during the week after the match.
     */
    public function canVote(Partido $match, User $user): bool
    {
        return ! $match->isCancelled()
            && $match->result()->exists()
            && $this->attended($match, $user);
    }

    public function myVote(Partido $match, User $user): ?string
    {
        $vote = $match->mvpVotes()->where('voter_user_id', $user->id)->first();

        return $vote === null ? null : $this->tokenOf($vote);
    }

    /**
     * Store or replace the account's vote. The candidate is expected to have
     * been validated against candidates() by the caller.
     *
     * @param  array{token: string, type: string, id: int, name: string}  $candidate
     */
    public function vote(Partido $match, User $user, array $candidate): void
    {
        MvpVote::updateOrCreate(
            ['match_id' => $match->id, 'voter_user_id' => $user->id],
            [
                'voted_user_id' => $candidate['type'] === 'user' ? $candidate['id'] : null,
                'voted_guest_id' => $candidate['type'] === 'guest' ? $candidate['id'] : null,
            ],
        );

        $this->syncResult($match);
    }

    /**
     * Votes per candidate token, most voted first.
     *
     * @return array<string, int>
     */
    public function tally(Partido $match): array
    {
        $tally = [];

        foreach ($match->mvpVotes()->get() as $vote) {
            $token = $this->tokenOf($vote);
            $tally[$token] = ($tally[$token] ?? 0) + 1;
        }

        arsort($tally);

        return $tally;
    }

    /**
     * Candidate tokens tied for first place; empty when nobody voted.
     *
     * @return array<int, string>
     */
    public function leaders(Partido $match): array
    {
        $tally = $this->tally($match);

        if ($tally === []) {
            return [];
        }

        $max = max($tally);

        return array_keys(array_filter($tally, fn (int $votes): bool => $votes === $max));
    }

    /**
     * Copy the tally into the stored result. A clear winner always takes the
     * MVP; when several are tied, an organizer's previous pick survives only
     * while it is still one of the leaders, so only live ties need breaking.
     */
    public function syncResult(Partido $match): void
    {
        $result = $match->result()->first();

        if ($result === null) {
            return;
        }

        $leaders = $this->leaders($match);
        $current = $result->mvp_user_id !== null
            ? 'user:'.$result->mvp_user_id
            : ($result->mvp_guest_id !== null ? 'guest:'.$result->mvp_guest_id : null);
        $tiebreak = (bool) $result->mvp_tiebreak;

        if (count($leaders) === 1) {
            $chosen = $leaders[0];
            $tiebreak = false;
        } elseif ($tiebreak && $current !== null && in_array($current, $leaders, true)) {
            // The organizer already broke this tie and it is still alive.
            $chosen = $current;
        } else {
            $chosen = null;
            $tiebreak = false;
        }

        [$userId, $guestId] = $this->idsForToken($chosen);

        if ($result->mvp_user_id === $userId
            && $result->mvp_guest_id === $guestId
            && (bool) $result->mvp_tiebreak === $tiebreak) {
            return;
        }

        $result->forceFill([
            'mvp_user_id' => $userId,
            'mvp_guest_id' => $guestId,
            'mvp_tiebreak' => $tiebreak,
        ])->save();
    }

    /**
     * Organizer's tie-break: pick one of the players tied for first.
     */
    public function breakTie(Partido $match, string $token): void
    {
        $result = $match->result()->first();

        if ($result === null) {
            return;
        }

        [$userId, $guestId] = $this->idsForToken($token);

        $result->forceFill([
            'mvp_user_id' => $userId,
            'mvp_guest_id' => $guestId,
            'mvp_tiebreak' => true,
        ])->save();
    }

    private function tokenOf(MvpVote $vote): string
    {
        return $vote->voted_user_id !== null
            ? 'user:'.$vote->voted_user_id
            : 'guest:'.$vote->voted_guest_id;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function idsForToken(?string $token): array
    {
        if ($token === null) {
            return [null, null];
        }

        [$type, $id] = explode(':', $token);
        $id = (int) $id;

        return $type === 'user' ? [$id, null] : [null, $id];
    }
}
