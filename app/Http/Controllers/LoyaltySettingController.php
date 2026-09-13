<?php

namespace App\Http\Controllers;

use App\Enums\CardTheme;
use App\Http\Requests\UpdateLoyaltySettingsRequest;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyTier;
use App\Services\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LoyaltySettingController extends Controller
{
    /**
     * The programme rules and the tier ladder, on one page.
     */
    public function edit(): View
    {
        $this->authorize('manage-loyalty');

        return view('loyalty.settings', [
            'settings' => LoyaltySetting::current(),
            'tiers' => LoyaltyTier::ordered()->withCount('customers')->get(),
            'themes' => CardTheme::cases(),
        ]);
    }

    /**
     * Save the rules and tiers, then re-tier every member against the new
     * thresholds so nobody sits on a tier they no longer qualify for.
     */
    public function update(UpdateLoyaltySettingsRequest $request, LoyaltyService $loyalty): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            LoyaltySetting::current()->update(Arr::except($validated, 'tiers'));

            foreach (array_values($validated['tiers']) as $index => $row) {
                $tier = filled($row['id'] ?? null) ? LoyaltyTier::find($row['id']) : null;

                if ((bool) ($row['remove'] ?? false)) {
                    $tier?->delete();

                    continue;
                }

                $attributes = [
                    'name' => $row['name'],
                    'min_spend' => $row['min_spend'],
                    'earn_multiplier' => $row['earn_multiplier'],
                    'card_theme' => $row['card_theme'],
                    'perks' => $row['perks'] ?? null,
                    'sort_order' => $index,
                ];

                $tier !== null ? $tier->update($attributes) : LoyaltyTier::create($attributes);
            }
        });

        $moved = $loyalty->refreshAllTiers();

        return redirect()
            ->route('loyalty.settings')
            ->with('status', 'Loyalty settings saved.'.($moved > 0 ? " {$moved} member(s) moved to a new tier." : ''));
    }
}
