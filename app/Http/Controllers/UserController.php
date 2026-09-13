<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\PlanLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Staff accounts. Restricted to super admins by the route middleware.
     */
    public function index(Request $request): View
    {
        return view('users.index', [
            'users' => User::query()
                ->when($request->filled('search'), function ($query) use ($request): void {
                    $term = $request->string('search')->toString();
                    $query->where(fn ($query) => $query->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
                })
                ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')->toString()))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'roles' => UserRole::cases(),
        ]);
    }

    public function create(): View
    {
        return view('users.form', [
            'user' => new User(['role' => UserRole::Admin, 'is_active' => true]),
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(StoreUserRequest $request, PlanLimits $limits): RedirectResponse
    {
        if ($blocked = $limits->reasonToBlock('max_users')) {
            return back()->withInput()->with('error', $blocked);
        }

        $user = User::create($request->validated());

        return redirect()->route('users.index')->with('status', "{$user->name} can now sign in as {$user->role->label()}.");
    }

    public function edit(User $user): View
    {
        return view('users.form', [
            'user' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        if ($user->is($request->user()) && ($data['role'] !== UserRole::SuperAdmin->value || $data['is_active'] === false)) {
            return back()->with('error', 'You cannot remove your own super admin access.');
        }

        $user->update($data);

        return redirect()->route('users.index')->with('status', "{$user->name} was updated.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        return back()->with('status', "{$user->name} was removed.");
    }
}
