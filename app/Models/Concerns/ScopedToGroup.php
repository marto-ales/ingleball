<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Route model binding for models that belong to a group: a row of another
 * group is simply not found, so no id can reach across groups.
 */
trait ScopedToGroup
{
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $query = $this->newQuery();

        if ($field === null && auth()->check() && auth()->user()->group_id !== null) {
            $query->where('group_id', auth()->user()->group_id);
        }

        return $query->where($this->getRouteKeyName(), $value)->first();
    }
}
