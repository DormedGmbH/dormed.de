<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $name) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ihre Anfrage bei SONORING DORMED',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.contact-form.customer',
        );
    }
}
