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

    /**
     * Per-attribute composite for a user: each characteristic blends the
     * player's self-assessment with the average the others gave them.
     *
     * @return array<string, float>
     */
    public function attributesForUser(User $user): array
    {
        $player = $user->player;
        $received = $user->ratingsReceived()->whereNotNull('rated_user_id');
        $count = $received->count();

        $attributes = [];

        foreach ((array) config('balance.attributes') as $key) {
            $self = (float) ($player?->{$key} ?? 5);
            $others = $count > 0 ? (float) $received->avg($key) : null;

            $attributes[$key] = $this->composite($self, $others, $count);
        }

        return $attributes;
    }

    /**
     * @return array<string, float>
     */
    public function attributesForGuest(Guest $guest): array
    {
        $attributes = [];

        foreach ((array) config('balance.attributes') as $key) {
            $attributes[$key] = (float) ($guest->{$key} ?? 5);
        }

        return $attributes;
    }

    /**
     * Weighted power of an attribute vector. Uses the given weights when
     * provided (the ones configured in the "Algoritmo" screen), falling back to
     * the defaults from config/balance.php. Speed weighs the most, then skill.
     *
     * @param  array<string, float>  $attributes
     * @param  array<string, float>|null  $weights
     */
    public function power(array $attributes, ?array $weights = null): float
    {
        $weights ??= (array) config('balance.attribute_weights');
        $power = 0.0;

        foreach ($weights as $key => $weight) {
            $power += (float) $weight * (float) ($attributes[$key] ?? 5);
        }

        return $power;
    }
}
