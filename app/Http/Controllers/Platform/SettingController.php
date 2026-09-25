<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SavePlatformSettingsRequest;
use App\Models\PlatformSetting;
use App\Models\Store;
use App\Services\RazorpayGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * How the platform itself is set up — the Razorpay account it charges its
 * shops through.
 */
class SettingController extends Controller
{
    public function edit(): View
    {
        $settings = PlatformSetting::current();

        return view('platform.settings.edit', [
            'settings' => $settings,
            'webhookUrl' => route('webhooks.razorpay'),
            'fromEnvironment' => blank($settings->razorpay_key_id) && filled(config('services.razorpay.key')),
            'subscribedStores' => Store::whereNotNull('razorpay_subscription_id')
                ->where('razorpay_subscription_status', 'active')
                ->count(),
        ]);
    }

    public function update(SavePlatformSettingsRequest $request): RedirectResponse
    {
        $settings = PlatformSetting::current();
        $settings->fill($request->validated())->save();

        return redirect()
            ->route('platform.settings.edit')
            ->with('status', 'Razorpay settings saved. Use "Test connection" to check them.');
    }

    /**
     * Ask Razorpay whether these keys work, so the platform finds out here
     * rather than when a shopkeeper tries to pay.
     */
    public function test(RazorpayGateway $razorpay): RedirectResponse
    {
        if (! $razorpay->enabled()) {
            return back()->with('error', 'Enter a key id and key secret first.');
        }

        $result = $razorpay->ping();

        return back()->with(
            $result['ok'] ? 'status' : 'error',
            $result['ok']
                ? 'Razorpay answered: the keys work.'
                : 'Razorpay refused the keys: '.$result['error'],
        );
    }
}
