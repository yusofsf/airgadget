<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $senderName,
        public string $senderPhone,
        public string $contactMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'پیام جدید فرم تماس ایرگجت');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.contact-message');
    }
}
