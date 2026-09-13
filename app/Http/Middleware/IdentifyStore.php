<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Support\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Works out which store a request belongs to from its subdomain, and puts it
 * into context for everything that follows.
 */
class IdentifyStore
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = (string) $request->route('store');

        // The subdomain is not an argument to any controller; it only decides
        // whose data this request sees.
        $request->route()?->forgetParameter('store');

        $store = Store::where('slug', $slug)->first();

        if ($store === null) {
            return response()->view('stores.unknown', ['host' => $request->getHost()], Response::HTTP_NOT_FOUND);
        }

        StoreContext::set($store);

        // So route() keeps producing this store's own addresses.
        URL::defaults(['store' => $store->slug]);

        // A closed store is not turned away here: EnsureSubscriptionIsPaid
        // sends it to the payment screen, which is how it gets reopened.
        return $next($request);
    }
}
