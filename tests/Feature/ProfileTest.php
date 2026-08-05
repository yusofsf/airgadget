<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get('/profile');

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $user->refresh();

    $this->assertSame('Test User', $user->name);
    $this->assertSame('test@example.com', $user->email);
    $this->assertNull($user->email_verified_at);
});

test('store account details can be updated by each authenticated user', function () {
    $user = User::factory()->create([
        'phone_number' => '09121111111',
        'postal_code' => '1111111111',
        'address' => 'آدرس قبلی',
    ]);

    $this->actingAs($user)
        ->patch(route('account.profile.update'), [
            'first_name' => 'مریم',
            'last_name' => 'احمدی',
            'phone_number' => '09122222222',
            'postal_code' => '1234567890',
            'address' => 'مشهد، آدرس جدید',
            'email' => 'maryam@example.com',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();
    expect($user->first_name)->toBe('مریم')
        ->and($user->last_name)->toBe('احمدی')
        ->and($user->phone_number)->toBe('09122222222')
        ->and($user->postal_code)->toBe('1234567890')
        ->and($user->address)->toBe('مشهد، آدرس جدید')
        ->and($user->email)->toBe('maryam@example.com');
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch('/profile', [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/profile');

    $this->assertNotNull($user->refresh()->email_verified_at);
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete('/profile', [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/');

    $this->assertGuest();
    $this->assertNull($user->fresh());
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect('/profile');

    $this->assertNotNull($user->fresh());
});
