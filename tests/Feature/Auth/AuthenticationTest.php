<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

test('login screen can be rendered', function () {
    $this->get('/login')->assertOk();
});

test('admin setup cannot be reached from the public web', function () {
    $this->post('/login/identify', ['phone' => '۰۹۱۲۰۰۰۰۰۰۰'])
        ->assertSessionHasNoErrors()
        ->assertRedirect('/login');

    $this->post('/admin/setup-password', [
        'password' => 'Admin12345',
        'password_confirmation' => 'Admin12345',
    ])->assertNotFound();

    expect(User::where('phone_number', '09120000000')->exists())->toBeFalse();
    $this->assertGuest();
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

test('admin can open the admin panel', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin')->assertOk();
});

test('admin panel remains available with a legacy order items table', function () {
    Schema::table('order_items', function ($table) {
        $table->dropColumn('quantity');
    });

    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get('/admin')->assertOk();

    $migration = require database_path('migrations/2026_07_28_000006_repair_legacy_order_items_quantity.php');
    $migration->up();

    expect(Schema::hasColumn('order_items', 'quantity'))->toBeTrue();
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
