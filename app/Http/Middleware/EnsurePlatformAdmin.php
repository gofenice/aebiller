<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the platform dashboard. Store staff signed in on a shop's subdomain
 * are not platform people and get nowhere near this.
 */
class EnsurePlatformAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('platform')->user();

        if ($admin === null) {
            return redirect()->guest(route('platform.login'));
        }

        abort_unless($admin->is_active, 403, 'This platform account has been switched off.');

        // The default guard is deliberately left alone: platform screens ask
        // for auth('platform') by name, and making it the default would leak
        // into store requests that share the process.
        return $next($request);
    }
}
