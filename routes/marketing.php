<?php

use App\Http\Controllers\Marketing\LandingController;
use App\Http\Controllers\Platform\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
|
| The bare domain (aebiller.com): what a shopkeeper sees before they are a
| customer. Signing up and signing in live on app.aebiller.com, and the
| platform is run from admin.aebiller.com.
|
*/

Route::get('/', LandingController::class)->name('marketing.home');

// Razorpay reports payments here. Kept on the bare domain so the address in
// the Razorpay dashboard never has to change. Signed, not authenticated, and
// exempt from CSRF in bootstrap/app.php.
Route::post('webhooks/razorpay', RazorpayWebhookController::class)->name('webhooks.razorpay');
