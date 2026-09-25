<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * What the platform itself runs on — today, the Razorpay account it charges
 * its shops through. There is only ever one row.
 *
 * Keys saved here stand in front of the ones in the server's .env, so the
 * platform can be set up from the admin screens rather than over SSH.
 */
#[Fillable(['razorpay_key_id', 'razorpay_key_secret', 'razorpay_webhook_secret'])]
class PlatformSetting extends Model
{
    /**
     * Read on most requests, so it is kept out of the database between saves.
     */
    public const CACHE_KEY = 'platform.settings';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'razorpay_key_secret' => 'encrypted',
            'razorpay_webhook_secret' => 'encrypted',
        ];
    }

    public static function current(): self
    {
        return static::query()->oldest('id')->first() ?? new self;
    }

    /**
     * Put the saved keys in front of the ones from the environment.
     *
     * Called on every request, including before the table exists, so a failure
     * to read settings must never stop the application booting.
     */
    public static function applyToConfig(): void
    {
        try {
            $settings = Cache::remember(
                self::CACHE_KEY,
                now()->addHour(),
                fn (): array => static::current()->only([
                    'razorpay_key_id', 'razorpay_key_secret', 'razorpay_webhook_secret',
                ]),
            );
        } catch (Throwable) {
            return;
        }

        $map = [
            'razorpay_key_id' => 'services.razorpay.key',
            'razorpay_key_secret' => 'services.razorpay.secret',
            'razorpay_webhook_secret' => 'services.razorpay.webhook_secret',
        ];

        foreach ($map as $column => $key) {
            if (filled($settings[$column] ?? null)) {
                config([$key => $settings[$column]]);
            }
        }
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Razorpay hands out test keys prefixed rzp_test_; anything else moves
     * real money.
     */
    public function isLiveKey(): bool
    {
        return filled($this->razorpay_key_id) && ! str_starts_with($this->razorpay_key_id, 'rzp_test_');
    }

    public function razorpayIsReady(): bool
    {
        return filled($this->razorpay_key_id ?: config('services.razorpay.key'))
            && filled($this->razorpay_key_secret ?: config('services.razorpay.secret'));
    }
}
