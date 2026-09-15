<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Partido extends Model
{
    use HasFactory;

    protected $table = 'matches';

    public const STATUS_OPEN = 'open';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_FINISHED = 'finished';

    protected $fillable = [
        'title', 'played_at', 'venue', 'size', 'status', 'created_by', 'locked_at',
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

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class, 'match_id');
    }

    public function result(): HasOne
    {
        return $this->hasOne(MatchResult::class, 'match_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(MatchTeam::class, 'match_id');
    }

    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class, 'match_id');
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
}
