<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'phone', 'group_id',
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(MatchEntry::class);
    }
}
