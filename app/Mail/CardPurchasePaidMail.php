<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CardPurchasePaidMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Order $order,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Compra de tarjetas confirmada',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.card-purchase-paid',
        );
    }
}
