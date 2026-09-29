<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Persistent configuration of the team balancing algorithm, editable by
 * organizers from the "Algoritmo" screen. Every group keeps its own copy.
 */
class AlgorithmSettings
{
    public const KEY = 'algorithm';

    /**
     * Weight each attribute gets from its position in the order: the first one
     * weighs the most, the second a bit less, and the rest share the remainder.
     *
     * @var array<int, float>
     */
    private const RANKED_WEIGHTS = [0.30, 0.25, 0.15, 0.15, 0.15];

    public function __construct(private ?int $groupId = null) {}

    /**
     * Copy of the settings for another group, keeping their own cache and row.
     */
    public function forGroup(int $groupId): self
    {
        return new self($groupId);
    }

    /**
     * Group these settings belong to; falls back to the default group so
     * console commands and tests work without an explicit group.
     */
    public function groupId(): int
    {
        return $this->groupId ??= Group::query()->orderBy('id')->value('id');
    }

    private function cacheKey(): string
    {
        return "algorithm.settings.{$this->groupId()}";
    }

    /**
     * @return array{order: array<int, string>, random_tie_break: bool, spread_goalies: bool, self_weight: float, weight_by_form: bool, form_span: float}
     */
    public function all(): array
    {
        $stored = Cache::remember($this->cacheKey(), now()->addDay(), function (): array {
            $row = Setting::where('group_id', $this->groupId())->where('key', self::KEY)->first();
            $decoded = $row?->value !== null ? json_decode($row->value, true) : [];

            return is_array($decoded) ? $decoded : [];
        });

        return $this->normalize($stored);
    }

    /**
     * @return array<int, string>
     */
    public function order(): array
    {
        return $this->all()['order'];
    }

    public function randomTieBreak(): bool
    {
        return $this->all()['random_tie_break'];
    }

    public function spreadGoalies(): bool
    {
        return $this->all()['spread_goalies'];
    }

    /**
     * Share of the self-assessment in the attribute profile; the rest is the
     * average of the organizers' evaluations.
     */
    public function selfWeight(): float
    {
        return $this->all()['self_weight'];
    }

    public function weightByForm(): bool
    {
        return $this->all()['weight_by_form'];
    }

    /**
     * How strongly the per-match ratings (form) can scale the profile, as a
     * fraction: 0.2 means the profile moves at most ±20%.
     */
    public function formSpan(): float
    {
        return $this->all()['form_span'];
    }

    /**
     * Attribute weights derived from the configured order.
     *
     * @return array<string, float>
     */
    public function weights(): array
    {
        $weights = [];

        foreach ($this->order() as $position => $attribute) {
            $weights[$attribute] = self::RANKED_WEIGHTS[$position] ?? 0.15;
        }

        return $weights;
    }

    /**
     * @param  array{order?: array<int, string>, random_tie_break?: bool, spread_goalies?: bool, self_weight?: float|int|string, weight_by_form?: bool, form_span?: float|int|string}  $data
     */
    public function update(array $data): void
    {
        Setting::updateOrCreate(
            ['group_id' => $this->groupId(), 'key' => self::KEY],
            ['value' => json_encode($this->normalize($data))],
        );

        Cache::forget($this->cacheKey());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{order: array<int, string>, random_tie_break: bool, spread_goalies: bool, self_weight: float, weight_by_form: bool, form_span: float}
     */
    private function normalize(array $data): array
    {
        $known = array_values((array) config('balance.attributes'));

        $order = array_values(array_filter(
            (array) ($data['order'] ?? []),
            fn ($attribute) => in_array($attribute, $known, true),
        ));

        foreach ($known as $attribute) {
            if (! in_array($attribute, $order, true)) {
                $order[] = $attribute;
            }
        }

        $selfWeight = $data['self_weight'] ?? config('balance.self_weight');

        return [
            'order' => $order === [] ? $known : $order,
            'random_tie_break' => (bool) ($data['random_tie_break'] ?? true),
            'spread_goalies' => (bool) ($data['spread_goalies'] ?? true),
            'self_weight' => max(0.0, min(1.0, (float) $selfWeight)),
            'weight_by_form' => (bool) ($data['weight_by_form'] ?? true),
            'form_span' => max(0.05, min(0.5, (float) ($data['form_span'] ?? config('balance.form_span', 0.2)))),
        ];
    }
}
