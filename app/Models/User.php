<?php

namespace App\Models;

use App\Models\Concerns\ScopedToGroup;
use App\Notifications\ResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'group_id',
        'is_organizer',
        'banned_at',
        'is_managed',
        'theme',
        'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_organizer' => 'boolean',
            'is_managed' => 'boolean',
            'banned_at' => 'datetime',
        ];
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPassword($token));
    }

    public function player(): HasOne
    {
        return $this->hasOne(Player::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Move the user to another group along with their own profile. Ratings,
     * matches and evaluations they took part in stay in the group they were
     * played or written in, so that group's history stays intact.
     */
    public function moveToGroup(Group $group): void
    {
        $this->forceFill(['group_id' => $group->id])->save();

        $this->player()->update(['group_id' => $group->id]);
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
}
