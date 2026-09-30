<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'organizer_user_id', 'rated_user_id',
        'speed', 'skill', 'passing', 'shooting', 'defense', 'goalkeeping',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'speed' => 'integer', 'skill' => 'integer', 'passing' => 'integer',
            'shooting' => 'integer', 'defense' => 'integer', 'goalkeeping' => 'integer',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_user_id');
    }

    public function ratedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_user_id');
    }
}
