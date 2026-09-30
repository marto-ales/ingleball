<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    protected $fillable = ['group_id', 'key', 'value'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
