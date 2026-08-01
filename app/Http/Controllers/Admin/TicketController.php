<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Storefront', [
            'view' => 'admin-tickets',
            'tickets' => Ticket::with('user:id,first_name,last_name,phone_number')
                ->withCount('messages')
                ->latest('updated_at')
                ->paginate(25),
        ]);
    }

    public function show(Ticket $ticket): Response
    {
        return Inertia::render('Storefront', [
            'view' => 'admin-ticket',
            'ticket' => $ticket->load(['user:id,first_name,last_name,phone_number,email', 'messages.user:id,first_name,last_name,is_admin', 'closedBy:id,first_name,last_name']),
        ]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        if ($ticket->status === 'closed') {
            throw ValidationException::withMessages(['message' => 'این تیکت بسته شده و امکان ارسال پاسخ جدید وجود ندارد.']);
        }
        $validated = $request->validate(['message' => ['required', 'string', 'max:5000']]);

        DB::transaction(function () use ($request, $ticket, $validated) {
            $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $validated['message']]);
            $ticket->update(['status' => 'answered']);
        });

        return back()->with('status', 'پاسخ مدیر ارسال شد.');
    }

    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        if ($ticket->status !== 'closed') {
            $ticket->update(['status' => 'closed', 'closed_by' => $request->user()->id, 'closed_at' => now()]);
        }

        return back()->with('status', 'تیکت بسته شد.');
    }
}
