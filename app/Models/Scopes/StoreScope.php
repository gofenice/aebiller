<?php

namespace App\Models\Scopes;

use App\Support\StoreContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query to the store being served.
 *
 * With no store in context — console work and platform screens — nothing is
 * added, so those can still look across stores on purpose. Code that writes
 * rows is protected separately: BelongsToStore refuses to create one without
 * a store.
 */
class StoreScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $storeId = StoreContext::id();

        if ($storeId === null) {
            return;
        }

        // Qualified, because these queries are often joined against another
        // table that carries a store_id of its own.
        $builder->where($model->qualifyColumn('store_id'), $storeId);
    }
}
