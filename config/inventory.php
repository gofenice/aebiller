<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */

    'currency_symbol' => env('APP_CURRENCY_SYMBOL', 'SAR '),
    'currency_code' => env('APP_CURRENCY_CODE', 'SAR'),

    /*
    |--------------------------------------------------------------------------
    | Store details printed on the tax invoice
    |--------------------------------------------------------------------------
    |
    | A Saudi simplified tax invoice has to carry the seller's name and VAT
    | registration number, so fill these in before going live.
    |
    */

    'store_vat_number' => env('STORE_VAT_NUMBER'),
    'store_address' => env('STORE_ADDRESS'),
    'store_phone' => env('STORE_PHONE'),

    /*
    |--------------------------------------------------------------------------
    | Expiry alerting
    |--------------------------------------------------------------------------
    |
    | Batches expiring within this many days are surfaced on the dashboard and
    | in the near-expiry report so they can be discounted or pulled off shelf.
    |
    */

    'expiry_alert_days' => (int) env('INVENTORY_EXPIRY_ALERT_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | VAT rates offered in the product form
    |--------------------------------------------------------------------------
    |
    | Saudi Arabia charges a standard 15% VAT. Zero-rated lines (exports and
    | the exempt categories) keep the 0% option available.
    |
    */

    'tax_rates' => [0, 15],

];
