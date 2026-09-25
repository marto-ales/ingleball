<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Partido extends Model
{
    use HasFactory;

    protected $table = 'matches';

    public const STATUS_OPEN = 'open';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_FINISHED = 'finished';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'title', 'played_at', 'venue', 'field_value', 'size', 'status', 'created_by', 'locked_at',
        'recurring', 'recurring_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'played_at' => 'datetime',
            'locked_at' => 'datetime',
            'size' => 'integer',
            'field_value' => 'integer',
            'recurring' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(MatchEntry::class, 'match_id');
    }

    public function guests(): HasManyThrough
    {
        return $this->hasManyThrough(
            Guest::class,
            MatchEntry::class,
            'match_id',
            'id',
            'id',
            'guest_id',
        );
    }

    public function result(): HasOne
    {
        return $this->hasOne(MatchResult::class, 'match_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(MatchTeam::class, 'match_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurring_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isActive(): bool
    {
        return $this->isOpen() || $this->isLocked();
    }

    /**
     * Cost per person = field value divided by the configured team sizes
     * (5v5 → field / 10, 4v4 → field / 8, 6v6 → field / 12). It follows the
     * match format, not the current signups.
     */
    public function costPerPerson(): ?int
    {
        $players = $this->capacity();

        if ($this->field_value === null || $players <= 0) {
            return null;
        }

        return (int) round($this->field_value / $players);
    }

    /**
     * Total going slots: double the team size (5v5 → 10, 4v4 → 8, 6v6 → 12).
     */
    public function capacity(): int
    {
        return max(0, (int) $this->size * 2);
    }

    /**
     * Whether another player may still sign up as a starter.
     */
    public function hasRoomForGoing(): bool
    {
        return $this->entries()->where('role', 'going')->count() < $this->capacity();
    }

    /**
     * Fill empty going slots with the earliest substitutes: when someone is
     * removed from the going list, the first substitute takes their place.
     */
    public function promoteSubstitutes(): void
    {
        $capacity = $this->capacity();
        $going = $this->entries()->where('role', 'going')->count();

        foreach ($this->entries()->where('role', 'substitute')->orderBy('id')->get() as $entry) {
            if ($going >= $capacity) {
                break;
            }

            $entry->update(['role' => MatchEntry::ROLE_GOING]);
            $going++;
        }
    }

    /**
     * Matches are finalized automatically once one hour has passed
     * since the scheduled start time. Returns true when the status changed.
     */
    public function autoFinish(): bool
    {
        if (! in_array($this->status, [self::STATUS_OPEN, self::STATUS_LOCKED], true)) {
            return false;
        }

        if ($this->played_at->copy()->addHours(1)->isFuture()) {
            return false;
        }

        $this->forceFill(['status' => self::STATUS_FINISHED])->save();

        return true;
    }
}
