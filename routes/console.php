<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Subscriptions: raise what is due, then warn and — after the grace period —
// close stores that have not paid.
Schedule::command('billing:generate-invoices')->dailyAt('01:00');
Schedule::command('billing:review-overdue')->dailyAt('01:15');

// Stores archived longer ago than the keeping period are swept away.
Schedule::command('stores:purge-archived')->dailyAt('02:00');

// Loyalty housekeeping, just after midnight once the day's bills are in.
Schedule::command('loyalty:expire-points')->dailyAt('00:10');
Schedule::command('loyalty:review-tiers')->dailyAt('00:20');
Schedule::command('loyalty:birthday-bonuses')->dailyAt('00:30');
