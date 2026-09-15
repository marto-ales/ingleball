<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id', 'scorer_user_id', 'scorer_guest_id', 'assister_user_id', 'assister_guest_id',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'match_id');
    }

    public function scorerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scorer_user_id');
    }

    public function scorerGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'scorer_guest_id');
    }

    public function assisterUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assister_user_id');
    }

    public function assisterGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'assister_guest_id');
    }
}
