<?php

namespace App\Models\Concerns;

use App\Models\Scopes\StoreScope;
use App\Models\Store;
use App\Support\StoreContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Marks a model as belonging to one store. Every query is filtered to the
 * store being served, and every new row is stamped with it, so no screen can
 * show — or write — another store's data.
 */
trait BelongsToStore
{
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope);

        static::creating(function (Model $model): void {
            if ($model->getAttribute('store_id') !== null) {
                return;
            }

            $storeId = StoreContext::id();

            if ($storeId === null) {
                // Without this guard a row written outside a store context
                // would belong to nobody and be invisible everywhere.
                throw new RuntimeException(sprintf(
                    'No store is in context, so a %s cannot be created. Wrap the work in StoreContext::runFor().',
                    class_basename($model),
                ));
            }

            $model->setAttribute('store_id', $storeId);
        });
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
