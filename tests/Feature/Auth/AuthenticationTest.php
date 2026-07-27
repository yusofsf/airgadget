<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('login screen can be rendered', function () {
    $this->get('/login')->assertOk();
});

test('admin can set a password on the first login', function () {
    $this->post('/login/identify', ['phone' => '۰۹۱۲۰۰۰۰۰۰۰'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/login');

    $response = $this->post('/admin/setup-password', [
        'password' => 'Admin12345',
        'password_confirmation' => 'Admin12345',
    ]);

    $admin = User::where('phone_number', '09120000000')->firstOrFail();

    expect($admin->is_admin)->toBeTrue()
        ->and(Hash::check('Admin12345', $admin->password))->toBeTrue();
    $this->assertAuthenticatedAs($admin);
    $response->assertRedirect('/admin');
});

test('admin can log in later using mobile and password', function () {
    $admin = User::factory()->create([
        'phone_number' => '09120000000',
        'password' => 'Admin12345',
        'is_admin' => true,
    ]);

    $response = $this->post('/login', [
        'phone' => '+98 912 000 0000',
        'password' => 'Admin12345',
    ]);

    $this->assertAuthenticatedAs($admin);
    $response->assertRedirect('/admin');
});

test('users can not authenticate with an invalid password', function () {
    User::factory()->create(['phone_number' => '09121111111']);

    $this->post('/login', [
        'phone' => '09121111111',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('phone');

    $this->assertGuest();
});

test('non admins can not open the admin panel', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});
