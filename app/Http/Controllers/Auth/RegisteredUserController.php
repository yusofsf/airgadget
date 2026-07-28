<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Support\Phone;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', [
            'phone' => Phone::normalize($request->query('phone')),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['phone_number' => Phone::normalize($request->input('phone_number'))]);
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone_number' => ['required', 'regex:/^09\d{9}$/', Rule::unique(User::class)],
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], ['phone_number.regex' => 'شماره موبایل را به‌صورت ۰۹xxxxxxxxx وارد کنید.', 'phone_number.unique' => 'این شماره موبایل قبلاً ثبت شده است.']);

        $isAdmin = hash_equals(Phone::normalize(config('admin.phone')), $validated['phone_number']);
        $attributes = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone_number' => $validated['phone_number'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => $isAdmin,
        ];
        if (Schema::hasColumn('users', 'name')) {
            $attributes['name'] = $validated['first_name'].' '.$validated['last_name'];
        }
        $user = User::create($attributes);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route($isAdmin ? 'admin' : 'account');
    }
}
