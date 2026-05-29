<?php

namespace App\Mail;

use App\Models\Card;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CardSentToPrintMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{name: string, path: string, label?: string}>  $evidenceFiles
     */
    public function __construct(
        public User $user,
        public Card $card,
        public PrintJob $printJob,
        public ?Order $order,
        public array $evidenceFiles = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Tarjeta enviada a impresión: {$this->card->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.card-sent-to-print',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return collect($this->evidenceFiles)
            ->filter(fn (array $file) => is_file($file['path']))
            ->map(fn (array $file) => Attachment::fromPath($file['path'])->as($file['name']))
            ->values()
            ->all();
    }
}
