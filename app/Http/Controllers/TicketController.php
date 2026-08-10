<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Storefront', [
            'view' => 'tickets',
            'tickets' => $request->user()->tickets()
                ->withCount('messages')
                ->latest('updated_at')
                ->paginate(10),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $ticket = DB::transaction(function () use ($request, $validated) {
            $ticket = $request->user()->tickets()->create([
                'number' => 'TK-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'subject' => $validated['subject'],
                'status' => 'open',
            ]);
            $ticket->messages()->create([
                'user_id' => $request->user()->id,
                'body' => $validated['message'],
            ]);

            return $ticket;
        });

        return redirect()->route('tickets.show', $ticket)->with('status', 'تیکت شما با موفقیت ثبت شد.');
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        $this->authorizeOwner($request, $ticket);

        return Inertia::render('Storefront', [
            'view' => 'ticket',
            'ticket' => $ticket->load(['messages.user:id,first_name,last_name,is_admin', 'closedBy:id,first_name,last_name']),
        ]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeOwner($request, $ticket);
        $this->ensureOpen($ticket);
        $validated = $request->validate(['message' => ['required', 'string', 'max:5000']]);

        DB::transaction(function () use ($request, $ticket, $validated) {
            $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $validated['message']]);
            $ticket->update(['status' => 'open']);
        });

        return back()->with('status', 'پاسخ شما ارسال شد.');
    }

    public function close(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorizeOwner($request, $ticket);
        if ($ticket->status !== 'closed') {
            $ticket->update(['status' => 'closed', 'closed_by' => $request->user()->id, 'closed_at' => now()]);
        }

        return back()->with('status', 'تیکت بسته شد.');
    }

    private function authorizeOwner(Request $request, Ticket $ticket): void
    {
        abort_unless($ticket->user_id === $request->user()->id, 403);
    }

    private function ensureOpen(Ticket $ticket): void
    {
        if ($ticket->status === 'closed') {
            throw ValidationException::withMessages(['message' => 'این تیکت بسته شده و امکان ارسال پاسخ جدید وجود ندارد.']);
        }
    }
}
