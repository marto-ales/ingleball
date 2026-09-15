<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id', 'name', 'phone',
        'speed', 'skill', 'passing', 'shooting', 'defense', 'overall',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'speed' => 'integer', 'skill' => 'integer', 'passing' => 'integer',
            'shooting' => 'integer', 'defense' => 'integer', 'overall' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'match_id');
    }
}
