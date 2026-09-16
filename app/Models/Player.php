<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'speed', 'skill', 'passing', 'shooting', 'defense', 'overall',
        'likes_goalie', 'goalkeeping',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'speed' => 'integer', 'skill' => 'integer', 'passing' => 'integer',
            'shooting' => 'integer', 'defense' => 'integer', 'overall' => 'integer',
            'likes_goalie' => 'boolean', 'goalkeeping' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
