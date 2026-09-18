<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchTeam extends Model
{
    use HasFactory;

    public const TEAM_A = 'A';

    public const TEAM_B = 'B';

    protected $fillable = ['match_id', 'team', 'user_id', 'guest_id'];

    public function match(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class);
    }
}
