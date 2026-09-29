<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'join_code',
        'whatsapp_group',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'whatsapp_group' => 'string',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(Partido::class);
    }

    public function guests(): HasMany
    {
        return $this->hasMany(Guest::class);
    }

    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    /**
     * Generate a fresh, unpredictable join code.
     */
    public static function randomJoinCode(): string
    {
        return Str::upper(Str::random(8));
    }
}
