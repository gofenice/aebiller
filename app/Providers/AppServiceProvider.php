<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerGates();
        $this->registerBladeDirectives();
    }

    /**
     * Formatting helpers used across the inventory screens.
     */
    protected function registerBladeDirectives(): void
    {
        Blade::directive('money', function (string $expression): string {
            return "<?php echo config('inventory.currency_symbol').number_format((float) ({$expression}), 2); ?>";
        });

        Blade::directive('qty', function (string $expression): string {
            return "<?php echo rtrim(rtrim(number_format((float) ({$expression}), 3, '.', ','), '0'), '.') ?: '0'; ?>";
        });
    }

    /**
     * Both roles run day-to-day inventory work; only a super admin may delete
     * records or touch user accounts and settings.
     */
    protected function registerGates(): void
    {
        Gate::define('manage-products', fn (User $user): bool => $user->is_active);
        Gate::define('manage-masters', fn (User $user): bool => $user->is_active);
        Gate::define('manage-stock', fn (User $user): bool => $user->is_active);
        Gate::define('run-till', fn (User $user): bool => $user->is_active);
        Gate::define('manage-expenses', fn (User $user): bool => $user->is_active);
        Gate::define('manage-customers', fn (User $user): bool => $user->is_active);

        // Paying the shop's own subscription is the owner's business.
        Gate::define('manage-subscription', fn (User $user): bool => $user->isSuperAdmin());

        Gate::define('delete-records', fn (User $user): bool => $user->isSuperAdmin());
        Gate::define('manage-users', fn (User $user): bool => $user->isSuperAdmin());
        // Programme rules and hand-entered points are money given away.
        Gate::define('manage-loyalty', fn (User $user): bool => $user->isSuperAdmin());
    }
}
