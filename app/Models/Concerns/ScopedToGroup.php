<?php

namespace App\Models\Concerns;

use App\Support\ActiveGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Route model binding for models that belong to a group: a row of another
 * group is simply not found, so no id can reach across groups. Models with a
 * group_id column filter on it; User overrides scopeInGroup to go through the
 * group_user membership instead.
 */
trait ScopedToGroup
{
    public function scopeInGroup(Builder $query, ?int $groupId): Builder
    {
        return $query->when(
            $groupId !== null,
            fn (Builder $query) => $query->where($this->qualifyColumn('group_id'), $groupId)
        );
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $query = $this->scopeInGroup($this->newQuery(), app(ActiveGroup::class)->id());

        return $query->where($field ?? $this->getRouteKeyName(), $value)->first();
    }
}
