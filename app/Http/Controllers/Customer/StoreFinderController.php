<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * "Which shop was mine again?" — points someone at their own subdomain, since
 * that is where a shop's staff actually sign in.
 */
class StoreFinderController extends Controller
{
    public function create(): View
    {
        return view('customer.find-shop', ['matches' => collect()]);
    }

    public function store(Request $request): RedirectResponse|View
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'max:150'],
        ]);

        $term = trim($validated['search']);

        // An address goes straight there.
        $slug = Str::slug(Str::lower(Str::after($term, '//')));
        $store = Store::where('slug', $slug)->first();

        if ($store !== null) {
            return redirect()->away($store->url().'/login');
        }

        // Otherwise an email address: show every shop it can sign in to.
        $matches = filter_var($term, FILTER_VALIDATE_EMAIL)
            ? Store::query()
                ->where('owner_email', $term)
                ->orWhereIn('id', User::withoutGlobalScopes()->where('email', $term)->pluck('store_id'))
                ->orderBy('name')
                ->get()
            : collect();

        return view('customer.find-shop', [
            'matches' => $matches,
            'searched' => $term,
        ]);
    }
}
