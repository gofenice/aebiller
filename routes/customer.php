<?php

use App\Http\Controllers\Customer\RegistrationController;
use App\Http\Controllers\Customer\StoreFinderController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer area
|--------------------------------------------------------------------------
|
| app.aebiller.com: where a shop signs itself up and where someone who has
| forgotten their shop's address is pointed back to it. The shop itself lives
| on its own subdomain once created.
|
*/

// Named rather than a path redirect, so it always lands on this subdomain
// rather than inheriting whatever root URL happens to be in play.
Route::get('/', fn () => redirect()->route('register'))->name('customer.home');

Route::get('register', [RegistrationController::class, 'create'])->name('register');
Route::post('register', [RegistrationController::class, 'store']);
Route::get('register/availability', [RegistrationController::class, 'availability'])->name('register.availability');
Route::get('welcome/{store}', [RegistrationController::class, 'welcome'])->name('register.welcome');

Route::get('sign-in', [StoreFinderController::class, 'create'])->name('customer.find');
Route::post('sign-in', [StoreFinderController::class, 'store']);
