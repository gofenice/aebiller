<?php

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureSubscriptionIsPaid;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\IdentifyStore;
use App\Http\Middleware\ResolveDisplayCurrency;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            $central = config('tenancy.central_domain');

            // The public site on the bare domain, aebiller.com…
            Route::middleware(['web', 'currency'])
                ->domain($central)
                ->group(base_path('routes/marketing.php'));

            // …the platform's own admin on admin.aebiller.com…
            Route::middleware('web')
                ->domain(config('tenancy.admin_subdomain').'.'.$central)
                ->group(base_path('routes/platform.php'));

            // …where a customer signs up or finds their shop again, quoted in
            // the same currency the public site showed them…
            Route::middleware(['web', 'currency'])
                ->domain(config('tenancy.app_subdomain').'.'.$central)
                ->group(base_path('routes/customer.php'));

            // …and every shop on its own subdomain, fathima.aebiller.com, which
            // is what the store middleware reads to know whose data to serve.
            // Registered last, so admin. and app. are never read as a shop.
            Route::middleware(['web', 'store', 'subscription'])
                ->domain('{store}.'.$central)
                ->group(base_path('routes/web.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'super_admin' => EnsureUserIsSuperAdmin::class,
            'store' => IdentifyStore::class,
            'subscription' => EnsureSubscriptionIsPaid::class,
            'platform' => EnsurePlatformAdmin::class,
            'currency' => ResolveDisplayCurrency::class,
        ]);

        // Razorpay signs its webhook rather than carrying a session token.
        $middleware->validateCsrfTokens(except: ['webhooks/razorpay']);

        // The store has to be known before anything asks who is signed in:
        // otherwise an unknown subdomain sends a visitor to a login page
        // instead of telling them there is no store at that address.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, IdentifyStore::class);

        $middleware->redirectGuestsTo('/login');

        // Someone already signed in who opens a sign-in page again belongs on
        // the dashboard of wherever they are — the platform, or their store.
        $middleware->redirectUsersTo(fn (Request $request): string => $request->getHost() === config('tenancy.central_domain')
            ? route('platform.home')
            : '/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
