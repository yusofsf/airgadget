<?php

use App\Models\Ticket;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('an authenticated user can create view reply to and close a ticket', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('tickets.store'), [
        'subject' => 'مشکل در پیگیری سفارش',
        'message' => 'وضعیت سفارش من به‌روزرسانی نشده است.',
    ]);

    $ticket = Ticket::with('messages')->firstOrFail();
    $response->assertRedirect(route('tickets.show', $ticket));
    expect($ticket->status)->toBe('open')
        ->and($ticket->messages)->toHaveCount(1)
        ->and($ticket->messages->first()->user_id)->toBe($user->id);

    $this->actingAs($user)->get(route('tickets.show', $ticket))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront')
            ->where('view', 'ticket')
            ->where('ticket.number', $ticket->number));

    $ticket->update(['status' => 'answered']);
    $this->actingAs($user)->post(route('tickets.reply', $ticket), ['message' => 'ممنون، اطلاعات بیشتری ارسال می‌کنم.'])
        ->assertSessionHasNoErrors();
    expect($ticket->fresh()->status)->toBe('open');

    $this->actingAs($user)->patch(route('tickets.close', $ticket))->assertSessionHasNoErrors();
    expect($ticket->fresh()->status)->toBe('closed')
        ->and($ticket->fresh()->closed_by)->toBe($user->id);

    $this->actingAs($user)->post(route('tickets.reply', $ticket), ['message' => 'پاسخ پس از بسته شدن'])
        ->assertSessionHasErrors('message');
});

test('a user cannot access another users ticket', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $ticket = Ticket::create(['user_id' => $owner->id, 'number' => 'TK-PRIVATE-1', 'subject' => 'خصوصی', 'status' => 'open']);

    $this->actingAs($otherUser)->get(route('tickets.show', $ticket))->assertForbidden();
    $this->actingAs($otherUser)->post(route('tickets.reply', $ticket), ['message' => 'دسترسی غیرمجاز'])->assertForbidden();
    $this->actingAs($otherUser)->patch(route('tickets.close', $ticket))->assertForbidden();
});

test('an admin can see all tickets reply and close them', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create(['is_admin' => true]);
    $ticket = Ticket::create(['user_id' => $user->id, 'number' => 'TK-ADMIN-1', 'subject' => 'نیاز به راهنمایی', 'status' => 'open']);
    $ticket->messages()->create(['user_id' => $user->id, 'body' => 'لطفاً راهنمایی کنید.']);

    $this->actingAs($admin)->get(route('admin.tickets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront')
            ->where('view', 'admin-tickets')
            ->where('tickets.data.0.number', $ticket->number));

    $this->actingAs($admin)->post(route('admin.tickets.reply', $ticket), ['message' => 'پاسخ پشتیبانی'])
        ->assertSessionHasNoErrors();
    expect($ticket->fresh()->status)->toBe('answered')
        ->and($ticket->messages()->reorder()->latest('id')->first()->user_id)->toBe($admin->id);

    $this->actingAs($admin)->patch(route('admin.tickets.close', $ticket))->assertSessionHasNoErrors();
    expect($ticket->fresh()->status)->toBe('closed')
        ->and($ticket->fresh()->closed_by)->toBe($admin->id);
});

test('guests cannot access the ticket system', function () {
    $this->get(route('tickets.index'))->assertRedirect(route('login'));
});
