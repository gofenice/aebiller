<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\StoreContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Show the staff sign-in screen.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Authenticate a super admin or admin.
     */
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Scoped to this store: the same email may be a member of staff at
        // more than one store, and each sign-in belongs to one of them.
        $attempt = [...$credentials, 'is_active' => true, 'store_id' => StoreContext::id()];

        if (! Auth::attempt($attempt, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match an active staff account.',
            ]);
        }

        $request->session()->regenerate();

        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * The signed link from sign-up: put the new owner straight into their shop.
     */
    public function welcome(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->store_id === StoreContext::id() && $user->is_active, 403);

        Auth::login($user);

        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('dashboard')->with('status', "Welcome to {$user->store->name}. Your shop is ready to use.");
    }

    /**
     * Sign the current user out.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
