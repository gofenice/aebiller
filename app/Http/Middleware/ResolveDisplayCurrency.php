<?php

namespace App\Http\Middleware;

use App\Support\DisplayCurrency;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Works out which currency the public site should quote, and remembers it.
 *
 * There is no route for switching: a ?currency= on any public address is the
 * switch, which keeps it working on the bare domain and on app. alike.
 */
class ResolveDisplayCurrency
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $currency = DisplayCurrency::resolve($request);

        DisplayCurrency::set($currency);

        $response = $next($request);

        // Only write the cookie when the answer has moved, so an ordinary page
        // view does not keep re-issuing it.
        if ($request->cookie(DisplayCurrency::COOKIE) !== $currency) {
            $response->headers->setCookie(DisplayCurrency::cookie($currency));
        }

        return $response;
    }
}
