<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\StoreContext;

/**
 * What the shop's plan lets it hold, and whether it has room for one more.
 *
 * A shop with no plan, or a plan that leaves a limit blank, is unlimited: a
 * missing figure must never be read as a cap of zero.
 */
class PlanLimits
{
    public function plan(): ?Plan
    {
        return StoreContext::get()?->plan;
    }

    /**
     * How many the shop holds against this limit right now.
     */
    public function used(string $key): int
    {
        return match ($key) {
            'max_products' => Product::query()->count(),
            'max_users' => User::query()->count(),
            'max_customers' => Customer::query()->count(),
            // The month to date, and a voided bill does not count against it.
            'max_monthly_bills' => Sale::query()
                ->whereNull('voided_at')
                ->whereBetween('sold_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            default => 0,
        };
    }

    /**
     * Whether one more may be added.
     */
    public function allows(string $key): bool
    {
        $plan = $this->plan();

        return $plan === null || $plan->allows($key, $this->used($key));
    }

    public function limit(string $key): ?int
    {
        return $this->plan()?->limitFor($key);
    }

    /**
     * Why the shop cannot add another, or null when it can. Names the figure
     * reached and the plan that lifts it, so the message is actionable rather
     * than just a refusal.
     */
    public function reasonToBlock(string $key): ?string
    {
        if ($this->allows($key)) {
            return null;
        }

        $plan = $this->plan();
        $label = strtolower(Plan::LIMITS[$key] ?? $key);
        $limit = number_format((int) $this->limit($key));
        $next = $this->nextPlanFor($key);

        $message = "Your {$plan?->name} plan covers {$limit} {$label}, and you have reached that.";

        return $next !== null
            ? $message." Move up to {$next->name} for ".strtolower($next->limitLabel($key)).'.'
            : $message.' Please get in touch to lift it.';
    }

    /**
     * The cheapest plan on offer that allows more of this than the shop's own.
     */
    public function nextPlanFor(string $key): ?Plan
    {
        $current = $this->limit($key);

        if ($current === null) {
            return null;
        }

        return Plan::query()
            ->active()
            ->public()
            ->ordered()
            ->get()
            ->first(fn (Plan $plan): bool => $plan->limitFor($key) === null || $plan->limitFor($key) > $current);
    }

    /**
     * Every limit with what is used against it, for a usage screen.
     *
     * @return array<string, array{label: string, used: int, limit: int|null, unlimited: bool}>
     */
    public function summary(): array
    {
        $summary = [];

        foreach (Plan::LIMITS as $key => $label) {
            $limit = $this->limit($key);

            $summary[$key] = [
                'label' => $label,
                'used' => $this->used($key),
                'limit' => $limit,
                'unlimited' => $limit === null,
            ];
        }

        return $summary;
    }
}
