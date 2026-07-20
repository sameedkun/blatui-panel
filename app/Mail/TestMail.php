<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent from the admin panel's mail settings page to prove a saved SMTP/Resend
 * configuration actually works — never queued, so failures surface immediately.
 */
class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(protected string $fromName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Test email from '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: "This is a test email from {$this->fromName} to confirm your mail settings are working.",
        );
    }
}
