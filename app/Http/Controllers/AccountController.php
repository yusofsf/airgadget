<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $request->merge([
            'phone_number' => Phone::normalize($request->input('phone_number')),
            'postal_code' => $this->englishDigits($request->input('postal_code')),
            'email' => $request->filled('email') ? strtolower((string) $request->input('email')) : null,
        ]);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'regex:/^09\d{9}$/', Rule::unique(User::class)->ignore($request->user()->id)],
            'postal_code' => ['required', 'regex:/^\d{10}$/'],
            'address' => ['required', 'string', 'max:1000'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique(User::class)->ignore($request->user()->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'phone_number.regex' => 'شماره موبایل را به‌صورت ۰۹xxxxxxxxx وارد کنید.',
            'phone_number.unique' => 'این شماره موبایل قبلاً ثبت شده است.',
            'password.confirmed' => 'تکرار رمز عبور یکسان نیست.',
        ]);

        $attributes = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone_number' => $validated['phone_number'],
            'postal_code' => $validated['postal_code'],
            'address' => $validated['address'],
            'email' => $validated['email'] ?? null,
            'is_admin' => hash_equals(Phone::normalize(config('admin.phone')), $validated['phone_number']),
        ];

        if (Schema::hasColumn('users', 'name')) {
            $attributes['name'] = $validated['first_name'].' '.$validated['last_name'];
        }

        if (! empty($validated['password'])) {
            $attributes['password'] = Hash::make($validated['password']);
        }

        $request->user()->update($attributes);

        return back()->with('status', 'اطلاعات حساب کاربری با موفقیت به‌روز شد.');
    }

    private function englishDigits(mixed $value): string
    {
        return strtr((string) $value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}
