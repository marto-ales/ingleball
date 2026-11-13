<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MvpVote extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id', 'voter_user_id', 'voted_user_id', 'voted_guest_id',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'match_id');
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voter_user_id');
    }

    public function votedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voted_user_id');
    }

    public function votedGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'voted_guest_id');
    }
}
