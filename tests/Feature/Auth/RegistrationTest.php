<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('registration screen can be rendered', function () {
    $this->get('/register')->assertOk();
});

test('new users can register without address postal code or email', function () {
    $response = $this->post('/register', [
        'first_name' => 'علی',
        'last_name' => 'رضایی',
        'phone_number' => '09121234567',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('account', absolute: false));

    $user = User::firstOrFail();
    expect($user->email)->toBeNull()
        ->and($user->address)->toBeNull()
        ->and($user->postal_code)->toBeNull();
});

test('email is accepted but remains optional during registration', function () {
    $this->post('/register', [
        'first_name' => 'سارا',
        'last_name' => 'محمدی',
        'phone_number' => '09125556666',
        'email' => 'SARA@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('account', absolute: false));

    expect(User::firstOrFail()->email)->toBe('sara@example.com');
});
