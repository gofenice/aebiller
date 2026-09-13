<?php

namespace App\Support;

use App\Models\Store;
use Closure;

/**
 * Which store the application is serving right now.
 *
 * A web request gets this from the subdomain (see IdentifyStore); console work
 * has no subdomain, so it sets the store itself with runFor(). Everything that
 * belongs to a store reads this to know what to show and what to write.
 */
class StoreContext
{
    protected static ?Store $store = null;

    public static function set(?Store $store): void
    {
        static::$store = $store;

        if ($store !== null) {
            $store->applyToConfig();
        }
    }

    public static function get(): ?Store
    {
        return static::$store;
    }

    public static function id(): ?int
    {
        return static::$store?->id;
    }

    public static function has(): bool
    {
        return static::$store !== null;
    }

    public static function forget(): void
    {
        static::$store = null;
    }

    /**
     * Run a piece of work as one store, then put back whatever store was in
     * context before — so a loop over every store leaves no trace behind.
     *
     * @template TReturn
     *
     * @param  Closure(Store): TReturn  $callback
     * @return TReturn
     */
    public static function runFor(Store $store, Closure $callback): mixed
    {
        $previous = static::$store;

        static::set($store);

        try {
            return $callback($store);
        } finally {
            static::set($previous);
        }
    }
}
