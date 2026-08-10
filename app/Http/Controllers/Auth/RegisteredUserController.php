<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'phone' => Phone::normalize($request->query('phone')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'phone_number' => Phone::normalize($request->input('phone_number')),
            'email' => $request->filled('email') ? strtolower((string) $request->input('email')) : null,
        ]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'regex:/^09\d{9}$/', Rule::unique(User::class)],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'phone_number.regex' => 'شماره موبایل را به‌صورت ۰۹xxxxxxxxx وارد کنید.',
            'phone_number.unique' => 'این شماره موبایل قبلاً ثبت شده است.',
        ]);

        $attributes = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone_number' => $validated['phone_number'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'is_admin' => false,
        ];

        if (Schema::hasColumn('users', 'name')) {
            $attributes['name'] = $validated['first_name'].' '.$validated['last_name'];
        }

        $user = User::create($attributes);

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('account');
    }
}
