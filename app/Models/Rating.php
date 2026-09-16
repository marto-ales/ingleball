<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'rater_user_id', 'rated_user_id', 'rated_guest_id', 'match_id',
        'speed', 'skill', 'passing', 'shooting', 'defense', 'overall',
        'goalkeeping',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'speed' => 'integer', 'skill' => 'integer', 'passing' => 'integer',
            'shooting' => 'integer', 'defense' => 'integer', 'overall' => 'integer',
            'goalkeeping' => 'integer',
        ];
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_user_id');
    }

    public function ratedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_user_id');
    }

    public function ratedGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'rated_guest_id');
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Partido::class);
    }
}
