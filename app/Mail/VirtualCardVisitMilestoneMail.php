<?php

namespace App\Mail;

use App\Models\Card;
use App\Models\LinkPage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VirtualCardVisitMilestoneMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public LinkPage $linkPage,
        public ?Card $card,
        public int $milestone,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tu tarjeta virtual llegó a {$this->milestone} visitas",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.virtual-card-visit-milestone',
        );
    }
}
