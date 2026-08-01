<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:30'],
            'message' => ['required', 'string', 'max:3000'],
        ]);

        Mail::to(config('mail.contact_to'))->send(new ContactMessage(
            $validated['name'],
            $validated['phone'],
            $validated['message'],
        ));

        return back()->with('status', 'پیام شما با موفقیت ارسال شد. به‌زودی با شما تماس می‌گیریم.');
    }
}
