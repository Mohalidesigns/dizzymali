<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'password' => ['required', 'confirmed', Password::defaults()->min(10)],
            // NDPA 2023: consent is captured explicitly and timestamped, not
            // implied by the act of registering.
            'privacy_consent' => ['accepted'],
            'whatsapp_opt_in' => ['sometimes', 'boolean'],
            'marketing_opt_in' => ['sometimes', 'boolean'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'country_code' => isset($validated['country_code']) ? strtoupper($validated['country_code']) : null,
            'password' => Hash::make($validated['password']),
            'whatsapp_opt_in' => (bool) ($validated['whatsapp_opt_in'] ?? false),
            'marketing_opt_in' => (bool) ($validated['marketing_opt_in'] ?? false),
            'privacy_consented_at' => now(),
        ]);

        $user->assignRole(UserRole::Customer->value);

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('order.wizard');
    }
}
