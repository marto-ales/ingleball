<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchEntry extends Model
{
    use HasFactory;

    public const ROLE_GOING = 'going';
    public const ROLE_SUBSTITUTE = 'substitute';
    public const ROLE_OUT = 'out';

    protected $fillable = [
        'match_id', 'user_id', 'guest_id', 'role', 'list_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['list_order' => 'integer'];
    }

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
