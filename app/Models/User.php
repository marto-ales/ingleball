<?php

namespace App\Models;

use App\Models\Concerns\ScopedToGroup;
use App\Notifications\ResetPassword;
use App\Support\ActiveGroup;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, ScopedToGroup;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'is_managed',
        'theme',
        'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * Profiles already loaded, keyed by group, so playerFor() does not query
     * the same profile twice while a page renders.
     *
     * @var array<int, Player|null>
     */
    private array $loadedPlayers = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_managed' => 'boolean',
        ];
    }

    public function isManaged(): bool
    {
        return (bool) $this->is_managed;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }

    /**
     * A user is not owned by a group: the same account belongs to as many
     * groups as they joined, and only their login and contact data is shared.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user')
            ->withPivot(['is_organizer', 'banned_at'])
            ->withTimestamps()
            ->orderBy('groups.name');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class);
    }

    /**
     * Player profile of this account inside a group. Attributes, goalkeeper
     * preference and self evaluation are per group: playing well in one does
     * not carry over to another.
     */
    public function players(): HasMany
    {
        return $this->hasMany(Player::class);
    }

    public function playerFor(?int $groupId = null): ?Player
    {
        $groupId ??= app(ActiveGroup::class)->id();

        if ($groupId === null) {
            return null;
        }

        if (! array_key_exists($groupId, $this->loadedPlayers)) {
            $this->loadedPlayers[$groupId] = $this->players->firstWhere('group_id', $groupId);
        }

        return $this->loadedPlayers[$groupId];
    }

    public function membershipIn(int $groupId): ?GroupMembership
    {
        return $this->memberships->firstWhere('group_id', $groupId);
    }

    public function isMemberOf(?int $groupId): bool
    {
        return $groupId !== null && $this->membershipIn($groupId) !== null;
    }

    /**
     * Organizing is per group: an organizer of one group is a plain player in
     * the others.
     */
    public function isOrganizerIn(?int $groupId): bool
    {
        return $groupId !== null && (bool) $this->membershipIn($groupId)?->is_organizer;
    }

    /**
     * A block belongs to the membership, so it keeps the person out of that
     * group without touching the groups they share with others.
     */
    public function isBannedIn(?int $groupId): bool
    {
        return $groupId !== null && (bool) $this->membershipIn($groupId)?->isBanned();
    }

    public function ratingsGiven(): HasMany
    {
        return $this->hasMany(Rating::class, 'rater_user_id');
    }

    public function ratingsReceived(): HasMany
    {
        return $this->hasMany(Rating::class, 'rated_user_id');
    }

    public function evaluationsGiven(): HasMany
    {
        return $this->hasMany(PlayerEvaluation::class, 'organizer_user_id');
    }

    public function evaluationsReceived(): HasMany
    {
        return $this->hasMany(PlayerEvaluation::class, 'rated_user_id');
    }

    public function matchesCreated(): HasMany
    {
        return $this->hasMany(Partido::class, 'created_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(MatchEntry::class);
    }

    public function matches(): BelongsToMany
    {
        return $this->belongsToMany(Partido::class, 'match_entries', 'user_id', 'match_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Route binding is scoped by membership: an account of another group is
     * simply not found, so no id can reach across groups.
     */
    public function scopeInGroup(Builder $query, ?int $groupId): Builder
    {
        return $query->when(
            $groupId !== null,
            fn (Builder $query) => $query->whereHas(
                'memberships',
                fn (Builder $query) => $query->where('group_id', $groupId)
            )
        );
    }
}
