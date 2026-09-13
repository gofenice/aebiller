<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Which currency the public site quotes prices in.
 *
 * A guess at first — from Cloudflare's country header, then the browser's
 * language — and the visitor's own choice from then on, remembered in a cookie
 * that carries across the bare domain and app. so a price does not change
 * between the pricing table and the sign-up form.
 */
class DisplayCurrency
{
    public const COOKIE = 'ae_currency';

    /**
     * A year: long enough that a returning visitor sees the prices they chose.
     */
    public const REMEMBER_MINUTES = 525600;

    protected static ?string $current = null;

    /**
     * The currencies plans may be priced in.
     *
     * @return array<int, string>
     */
    public static function offered(): array
    {
        return config('tenancy.pricing_currencies');
    }

    public static function base(): string
    {
        return config('tenancy.base_currency');
    }

    public static function supports(?string $code): bool
    {
        return $code !== null && in_array(strtoupper($code), static::offered(), true);
    }

    /**
     * The currency in play for this request.
     */
    public static function current(): string
    {
        return static::$current ?? static::base();
    }

    public static function set(string $code): void
    {
        static::$current = strtoupper($code);
    }

    public static function forget(): void
    {
        static::$current = null;
    }

    /**
     * The switcher wins, then what was chosen before, then where the visitor
     * appears to be, and the base currency if none of that says anything.
     */
    public static function resolve(Request $request): string
    {
        foreach ([$request->query('currency'), $request->cookie(static::COOKIE)] as $chosen) {
            if (is_string($chosen) && static::supports($chosen)) {
                return strtoupper($chosen);
            }
        }

        return static::guess($request) ?? static::base();
    }

    /**
     * Cloudflare puts the visitor's country in CF-IPCountry; without it, the
     * region in the browser's own language header is the next best hint.
     */
    public static function guess(Request $request): ?string
    {
        $countries = config('tenancy.country_currency');

        $country = strtoupper((string) $request->header('CF-IPCountry'));

        if ($country !== '' && isset($countries[$country]) && static::supports($countries[$country])) {
            return $countries[$country];
        }

        $region = static::regionFromLanguage((string) $request->header('Accept-Language'));

        if ($region !== null && isset($countries[$region]) && static::supports($countries[$region])) {
            return $countries[$region];
        }

        return null;
    }

    /**
     * "en-GB,en;q=0.9" is Britain; "en" on its own tells us nothing.
     */
    protected static function regionFromLanguage(string $header): ?string
    {
        foreach (explode(',', $header) as $part) {
            $tag = trim(explode(';', $part)[0]);

            if (preg_match('/^[a-z]{2,3}-([A-Za-z]{2})$/i', $tag, $matches) === 1) {
                return strtoupper($matches[1]);
            }
        }

        return null;
    }

    /**
     * Remembered for the whole domain, so a choice made on the pricing table is
     * still in force on app. when the shop signs up.
     */
    public static function cookie(string $code): Cookie
    {
        $central = config('tenancy.central_domain');

        return cookie(
            name: static::COOKIE,
            value: strtoupper($code),
            minutes: static::REMEMBER_MINUTES,
            domain: str_contains($central, '.') ? '.'.$central : null,
        );
    }

    public static function symbolOf(string $code): string
    {
        return config('tenancy.currencies.'.$code.'.symbol', $code.' ');
    }

    public static function nameOf(string $code): string
    {
        return config('tenancy.currencies.'.$code.'.name', $code);
    }

    /**
     * Whole riyals on the pricing table; the decimals belong on invoices.
     */
    public static function format(float $amount, ?string $code = null, int $decimals = 0): string
    {
        $code ??= static::current();

        return static::symbolOf($code).number_format($amount, $decimals);
    }
}
