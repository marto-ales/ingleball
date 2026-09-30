<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /**
     * The accounts that belong to this group. One account can appear in many
     * groups: what it plays, rates and is evaluated in is not shared.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_user')
            ->withPivot(['is_organizer', 'banned_at'])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class);
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

    public function memberCount(): int
    {
        return $this->memberships()->count();
    }

    /**
     * The accounts of one group, for every list, dropdown and membership
     * check: a user is in a group through group_user, not through a column.
     */
    public static function membersQuery(int $groupId): Builder
    {
        return User::query()
            ->whereHas('memberships', fn (Builder $query) => $query->where('group_id', $groupId));
    }

    /**
     * Generate a fresh, unpredictable join code.
     */
    public static function randomJoinCode(): string
    {
        return Str::upper(Str::random(8));
    }
}
