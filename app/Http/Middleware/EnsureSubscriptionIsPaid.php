<?php

namespace App\Http\Middleware;

use App\Support\StoreContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A store that has not paid can do nothing but pay.
 *
 * Everything is sent to the subscription screen, apart from the few routes
 * needed to get out of that state — paying, signing in and out — and the
 * customer-facing links, which are a shopper's receipt rather than shop work.
 */
class EnsureSubscriptionIsPaid
{
    /**
     * @var array<int, string>
     */
    protected const ALWAYS_ALLOWED = [
        'subscription.show',
        'subscription.pay',
        'subscription.callback',
        'subscription.auto-charge.enable',
        'subscription.auto-charge.disable',
        'login',
        'logout',
        'store.welcome',
        'bill.show',
        'member.show',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $store = StoreContext::get();

        if ($store === null || ! $store->isLocked()) {
            return $next($request);
        }

        if ($request->routeIs(self::ALWAYS_ALLOWED)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'This account is locked until the subscription is paid.'], 402);
        }

        return redirect()->route('subscription.show');
    }
}
