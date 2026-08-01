<?php

use App\Mail\ContactMessage;
use Illuminate\Support\Facades\Mail;

test('a visitor can send a contact message to store support', function () {
    Mail::fake();
    config(['mail.contact_to' => 'support@airgadget.ir']);

    $response = $this->post(route('contact.store'), [
        'name' => 'کاربر آزمایشی',
        'phone' => '09121234567',
        'message' => 'برای پیگیری سفارش به راهنمایی نیاز دارم.',
    ]);

    $response->assertRedirect()
        ->assertSessionHas('status');

    Mail::assertSent(ContactMessage::class, fn (ContactMessage $mail) =>
        $mail->hasTo('support@airgadget.ir')
        && $mail->senderName === 'کاربر آزمایشی'
        && $mail->senderPhone === '09121234567'
    );
});

test('contact messages require a name, phone and message', function () {
    Mail::fake();

    $this->post(route('contact.store'), [])
        ->assertSessionHasErrors(['name', 'phone', 'message']);

    Mail::assertNothingSent();
});
