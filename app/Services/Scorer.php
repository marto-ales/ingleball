<?php

namespace App\Services;

use App\Models\Guest;
use App\Models\MatchEntry;
use App\Models\Partido;
use App\Models\Rating;
use App\Models\User;
use Illuminate\Support\Collection;

class Scorer
{
    public function __construct(private AlgorithmSettings $settings) {}

    /**
     * Attribute profile of a registered player: the self-assessment blended
     * with the organizers' evaluations, then scaled by recent form.
     *
     * @param  array{general: float|null, group: float|null, multiplier: float}|null  $form
     * @return array<string, float>
     */
    public function attributesForUser(User $user, ?array $form = null): array
    {
        $multiplier = ($form ?? $this->formForUser($user))['multiplier'];

        $attributes = [];

        foreach ($this->blendedAttributes($user) as $key => $value) {
            $attributes[$key] = round(max(0.0, min(10.0, $value * $multiplier)), 2);
        }

        return $attributes;
    }

    /**
     * Blended goalkeeper skill (self + organizers), without the form scaling.
     * Used for display only.
     */
    public function goalkeepingForUser(User $user): float
    {
        $self = (float) ($user->player?->goalkeeping ?? 5);
        $others = $this->organizerAverage($user, 'goalkeeping');

        return round($others === null ? $self : $this->blend($self, $others), 2);
    }

    /**
     * Recent form: the general score the player received over their last
     * finished matches. The multiplier scales the profile by that rating:
     * each point above or below the neutral level (stored 5) moves it up to
     * ±20%.
     *
     * @return array{general: float|null, group: float|null, multiplier: float}
     */
    public function formForUser(User $user): array
    {
        $matchIds = $this->recentMatchIds($user, max(1, (int) config('balance.form_window')));

        if ($matchIds->isEmpty()) {
            return ['general' => null, 'group' => null, 'multiplier' => 1.0];
        }

        $general = Rating::whereIn('match_id', $matchIds)
            ->where('rated_user_id', $user->id)
            ->avg('overall');

        $group = Rating::whereIn('match_id', $matchIds)->avg('overall');

        return [
            'general' => $general !== null ? round((float) $general, 2) : null,
            'group' => $group !== null ? round((float) $group, 2) : null,
            'multiplier' => $this->multiplier($general),
        ];
    }

    /**
     * Weighted 1–10 score of a player's profile, used by the leaderboard.
     *
     * @param  array{general: float|null, group: float|null, multiplier: float}|null  $form
     */
    public function scoreForUser(User $user, ?array $form = null): float
    {
        return round($this->power($this->attributesForUser($user, $form), $this->settings->weights()), 2);
    }

    /**
     * The general ratings this player received, one per finished match within
     * the form window, newest first. Used to explain the Rendimiento column.
     *
     * @return Collection<int, array{match: Partido, overall: float}>
     */
    public function ratingDetailsForUser(User $user): Collection
    {
        $matchIds = $this->recentMatchIds($user, max(1, (int) config('balance.form_window')));

        if ($matchIds->isEmpty()) {
            return collect();
        }

        return Rating::whereIn('match_id', $matchIds)
            ->where('rated_user_id', $user->id)
            ->with('match')
            ->get()
            ->sortByDesc(fn (Rating $rating): int => (int) $rating->match?->played_at?->getTimestamp())
            ->values()
            ->map(fn (Rating $rating): array => [
                'match' => $rating->match,
                'overall' => (float) $rating->overall,
            ]);
    }

    public function forGuest(Guest $guest): float
    {
        return (float) ($guest->overall ?? 5);
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

    /**
     * Self-assessment blended with the organizers' average, without form.
     *
     * @return array<string, float>
     */
    private function blendedAttributes(User $user): array
    {
        $player = $user->player;
        $attributes = [];

        foreach ((array) config('balance.attributes') as $key) {
            $self = (float) ($player?->{$key} ?? 5);
            $others = $this->organizerAverage($user, $key);

            $attributes[$key] = $others === null ? $self : $this->blend($self, $others);
        }

        return $attributes;
    }

    private function blend(float $self, float $others): float
    {
        $selfWeight = $this->settings->selfWeight();

        return $self * $selfWeight + $others * (1 - $selfWeight);
    }

    private function organizerAverage(User $user, string $column): ?float
    {
        if ($user->evaluationsReceived()->count() === 0) {
            return null;
        }

        return (float) $user->evaluationsReceived()->avg($column);
    }

    /**
     * Ids of the player's last finished matches, newest first.
     *
     * @return Collection<int, int>
     */
    private function recentMatchIds(User $user, int $window): Collection
    {
        return $user->entries()
            ->where('role', MatchEntry::ROLE_GOING)
            ->with('match')
            ->get()
            ->map(fn (MatchEntry $entry): ?Partido => $entry->match)
            ->filter(fn (?Partido $match): bool => $match !== null && $match->isFinished())
            ->sortByDesc('played_at')
            ->take($window)
            ->pluck('id')
            ->unique()
            ->values();
    }

    private function multiplier(?float $general): float
    {
        if (! $this->settings->weightByForm() || $general === null) {
            return 1.0;
        }

        // The stored overall is 0-10; centered on 5, each point above or below
        // neutral moves the multiplier by span/5, capped at ±span.
        $span = $this->settings->formSpan();
        $weight = (($general - 5) / 5) * $span;

        return max(
            1 - $span,
            min(1 + $span, 1 + $weight),
        );
    }
}
