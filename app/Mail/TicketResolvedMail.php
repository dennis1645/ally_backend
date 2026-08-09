<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketResolvedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $ticket;
    public $finalMessage;

    public function __construct($ticket, $finalMessage)
    {
        $this->ticket = $ticket;
        $this->finalMessage = $finalMessage;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[{$this->ticket->ticket_number}] Tiket Selesai / Resolved",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.support.resolved', // Mengarah ke file blade
        );
    }
}