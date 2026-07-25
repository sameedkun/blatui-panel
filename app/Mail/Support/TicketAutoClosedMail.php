<?php

namespace App\Mail\Support;

use App\Enum\MailPurpose;
use App\Mail\Concerns\HasMailPurpose;
use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Mailable informing a requester their ticket auto-closed from inactivity,
 * using {@see MailPurpose::Support}.
 */
class TicketAutoClosedMail extends Mailable
{
    use HasMailPurpose, Queueable, SerializesModels;

    protected MailPurpose $purpose = MailPurpose::Support;

    public function __construct(public Ticket $ticket, public int $inactiveDays) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Ticket #{$this->ticket->id} Closed Due to Inactivity",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.support.ticket-auto-closed',
            with: [
                'ticket' => $this->ticket,
                'inactiveDays' => $this->inactiveDays,
            ],
        );
    }
}
