<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Central domain
    |--------------------------------------------------------------------------
    |
    | The domain the platform itself answers on (pgbiller.com). Every store
    | lives on a subdomain of it — fathima.pgbiller.com — and the subdomain is
    | what tells the application which store's data to serve.
    |
    | Locally, leave this as "localhost": browsers resolve anything.localhost
    | to 127.0.0.1 on their own, so fathima.localhost:8000 just works.
    |
    */

    'central_domain' => env('APP_DOMAIN', 'localhost'),

    /*
    |--------------------------------------------------------------------------
    | Platform name
    |--------------------------------------------------------------------------
    |
    | The business behind the stores — shown on the platform dashboard and on
    | the "no store here" page. A store's own screens show the store's name.
    |
    */

    'platform_name' => env('APP_PLATFORM_NAME', 'AE Biller'),

    /*
    |--------------------------------------------------------------------------
    | The platform's own subdomains
    |--------------------------------------------------------------------------
    |
    | The bare domain carries the public site. These two carry the parts of it
    | that are not a shop: where the platform is run from, and where a new
    | customer signs up or finds their way back to their own shop.
    |
    */

    'admin_subdomain' => env('APP_ADMIN_SUBDOMAIN', 'admin'),
    'app_subdomain' => env('APP_CUSTOMER_SUBDOMAIN', 'app'),

    /*
    |--------------------------------------------------------------------------
    | Free trial
    |--------------------------------------------------------------------------
    |
    | How many days a shop gets after signing up itself, before the first
    | invoice falls due.
    |
    */

    'trial_days' => (int) env('APP_TRIAL_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Reserved subdomains
    |--------------------------------------------------------------------------
    |
    | Names a store may never be given, because they are needed for the
    | platform itself, for mail, or are too easily mistaken for it.
    |
    */

    'reserved_subdomains' => [
        'www', 'admin', 'api', 'app', 'mail', 'smtp', 'imap', 'pop', 'ftp', 'ns1', 'ns2',
        'cdn', 'static', 'assets', 'files', 'img', 'media', 'billing', 'pay', 'payments',
        'portal', 'support', 'help', 'status', 'blog', 'shop', 'store', 'stores', 'login',
        'signup', 'register', 'account', 'accounts', 'dashboard', 'platform', 'test', 'dev',
        'staging', 'demo', 'mx', 'email', 'webmail', 'secure', 'my',
    ],

    /*
    |--------------------------------------------------------------------------
    | Store defaults
    |--------------------------------------------------------------------------
    |
    | What a newly created store starts with, before its owner changes them.
    |
    */

    'defaults' => [
        'currency_code' => 'USD',
        'currency_symbol' => '$',
        'timezone' => 'Asia/Riyadh',
        'expiry_alert_days' => 30,
        'tax_rates' => [0, 15],
    ],

    /*
    |--------------------------------------------------------------------------
    | What the platform charges in
    |--------------------------------------------------------------------------
    |
    | Plans are priced in the base currency, and a price may be set by hand for
    | each of the currencies below — no exchange rates, so a price only ever
    | changes when you change it. A currency with no price for a plan falls
    | back to the base one.
    |
    */

    'base_currency' => env('APP_BASE_CURRENCY', 'USD'),

    'pricing_currencies' => ['USD', 'SAR', 'AED', 'INR', 'GBP', 'EUR'],

    /*
    |--------------------------------------------------------------------------
    | Guessing a visitor's currency
    |--------------------------------------------------------------------------
    |
    | Cloudflare puts the visitor's country in CF-IPCountry, which is enough to
    | pre-select a currency. It is only ever a suggestion: the switcher wins,
    | and the choice is remembered in a cookie.
    |
    */

    'country_currency' => [
        'US' => 'USD', 'SA' => 'SAR', 'AE' => 'AED', 'IN' => 'INR', 'GB' => 'GBP',
        'QA' => 'QAR', 'KW' => 'KWD', 'BH' => 'BHD', 'OM' => 'OMR', 'EG' => 'EGP',
        'PK' => 'PKR', 'BD' => 'BDT', 'LK' => 'LKR',
        'IE' => 'EUR', 'DE' => 'EUR', 'FR' => 'EUR', 'ES' => 'EUR', 'IT' => 'EUR',
        'NL' => 'EUR', 'BE' => 'EUR', 'PT' => 'EUR', 'AT' => 'EUR', 'FI' => 'EUR',
        'GR' => 'EUR', 'CY' => 'EUR', 'MT' => 'EUR', 'LU' => 'EUR', 'SK' => 'EUR',
        'SI' => 'EUR', 'EE' => 'EUR', 'LV' => 'EUR', 'LT' => 'EUR', 'HR' => 'EUR',
    ],

    /*
    |--------------------------------------------------------------------------
    | Currencies a store can be set to
    |--------------------------------------------------------------------------
    |
    | Offered when a store is created; the symbol is what its screens, receipts
    | and loyalty cards print.
    |
    */

    'currencies' => [
        'SAR' => ['symbol' => 'SAR ', 'name' => 'Saudi riyal'],
        'AED' => ['symbol' => 'AED ', 'name' => 'UAE dirham'],
        'QAR' => ['symbol' => 'QAR ', 'name' => 'Qatari riyal'],
        'KWD' => ['symbol' => 'KWD ', 'name' => 'Kuwaiti dinar'],
        'BHD' => ['symbol' => 'BHD ', 'name' => 'Bahraini dinar'],
        'OMR' => ['symbol' => 'OMR ', 'name' => 'Omani rial'],
        'EGP' => ['symbol' => 'EGP ', 'name' => 'Egyptian pound'],
        'INR' => ['symbol' => '₹', 'name' => 'Indian rupee'],
        'PKR' => ['symbol' => 'Rs ', 'name' => 'Pakistani rupee'],
        'BDT' => ['symbol' => '৳', 'name' => 'Bangladeshi taka'],
        'LKR' => ['symbol' => 'Rs ', 'name' => 'Sri Lankan rupee'],
        'USD' => ['symbol' => '$', 'name' => 'US dollar'],
        'EUR' => ['symbol' => '€', 'name' => 'Euro'],
        'GBP' => ['symbol' => '£', 'name' => 'Pound sterling'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Time zones offered for a store
    |--------------------------------------------------------------------------
    */

    'timezones' => [
        'Asia/Riyadh', 'Asia/Dubai', 'Asia/Qatar', 'Asia/Kuwait', 'Asia/Bahrain', 'Asia/Muscat',
        'Africa/Cairo', 'Asia/Kolkata', 'Asia/Karachi', 'Asia/Dhaka', 'Asia/Colombo',
        'Europe/London', 'UTC',
    ],

];
