<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use App\Support\Phone;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        if ($request->boolean('change')) {
            $request->session()->forget('login.phone');
        }

        $phone = $request->session()->get('login.phone');
        $adminPhone = Phone::normalize(config('admin.phone'));
        $userExists = $phone ? User::where('phone_number', $phone)->exists() : false;
        $mode = ! $phone ? 'identify' : (($phone === $adminPhone && ! $userExists) ? 'setup' : 'login');

        return Inertia::render('Auth/Login', [
            'status' => session('status'),
            'mode' => $mode,
            'phone' => $phone,
        ]);
    }

    public function identify(Request $request): RedirectResponse
    {
        $phone = Phone::normalize($request->input('phone'));

        if (! Phone::isValid($phone)) {
            throw ValidationException::withMessages(['phone' => 'شماره موبایل را به‌صورت ۰۹xxxxxxxxx وارد کنید.']);
        }

        $request->session()->put('login.phone', $phone);

        return redirect()->route('login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        if (hash_equals(Phone::normalize(config('admin.phone')), $user->phone_number) && ! $user->is_admin) {
            $user->forceFill(['is_admin' => true])->save();
        }

        return redirect()->intended($user->is_admin ? route('admin', absolute: false) : route('account', absolute: false));
    }

    public function setupAdminPassword(Request $request): RedirectResponse
    {
        $phone = Phone::normalize($request->session()->get('login.phone'));
        $adminPhone = Phone::normalize(config('admin.phone'));

        if (! Phone::isValid($adminPhone) || ! hash_equals($adminPhone, $phone) || User::where('phone_number', $phone)->exists()) {
            throw ValidationException::withMessages(['phone' => 'امکان فعال‌سازی ادمین با این شماره وجود ندارد.']);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'password.confirmed' => 'تکرار رمز عبور یکسان نیست.',
            'password.min' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
        ]);

        $attributes = [
            'first_name' => 'مدیر',
            'last_name' => 'فروشگاه',
            'phone_number' => $phone,
            'email' => 'admin-'.substr(hash('sha256', $phone), 0, 12).'@airgadget.local',
            'password' => Hash::make($validated['password']),
            'is_admin' => true,
        ];

        if (Schema::hasColumn('users', 'name')) {
            $attributes['name'] = 'مدیر فروشگاه';
        }

        $user = User::create($attributes);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('admin');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
