<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\User;

class Scorer
{
    /**
     * Blend a player's self-assessment with the peer ratings they received.
     * When there are no peer ratings yet, only the self-assessment counts.
     */
    public function composite(float $self, ?float $others, int $count): float
    {
        if ($count > 0 && $others !== null) {
            $selfWeight = (float) config('balance.self_weight');
            $othersWeight = (float) config('balance.others_weight');

            return $self * $selfWeight + $others * $othersWeight;
        }

        return $self;
    }

    public function forUser(User $user): float
    {
        $self = (float) ($user->player?->overall ?? 5);

        $received = $user->ratingsReceived()->whereNotNull('rated_user_id');
        $count = $received->count();
        $others = $count > 0 ? (float) $received->avg('overall') : null;

        return $this->composite($self, $others, $count);
    }

    public function forGuest(Guest $guest): float
    {
        return (float) ($guest->overall ?? 5);
    }
}
