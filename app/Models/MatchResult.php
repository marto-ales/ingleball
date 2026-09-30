<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id', 'winner', 'diff', 'mvp_user_id', 'mvp_guest_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'diff' => 'integer',
        ];
    }

    protected function summary(): Attribute
    {
        return Attribute::get(fn (): string => $this->winner === null
            ? 'Empate'
            : 'Ganó Equipo '.$this->winner.($this->diff > 0 ? ' por '.$this->diff : ''));
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(Partido::class, 'match_id');
    }

    public function mvpUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mvp_user_id');
    }

    public function mvpGuest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'mvp_guest_id');
    }
}
