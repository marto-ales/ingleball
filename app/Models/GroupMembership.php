<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One account's place inside one group. The account itself (username, password
 * and contact data) is shared across every group the person belongs to; the
 * organizer flag and the block belong to this membership only, so someone can
 * run one group and be a plain player in another.
 */
class GroupMembership extends Model
{
    protected $table = 'group_user';

    protected $fillable = [
        'group_id',
        'user_id',
        'is_organizer',
        'banned_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_organizer' => 'boolean',
            'banned_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }
}
