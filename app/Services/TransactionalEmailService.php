<?php

namespace App\Services;

use App\Mail\CardPurchasePaidMail;
use App\Mail\CardSentToPrintMail;
use App\Mail\PasswordChangedMail;
use App\Mail\VirtualCardVisitMilestoneMail;
use App\Mail\WelcomeUserMail;
use App\Models\AnalyticsEvent;
use App\Models\Card;
use App\Models\EmailNotification;
use App\Models\LinkPage;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

class TransactionalEmailService
{
    public function sendWelcome(User $user): void
    {
        if (! $user->email) {
            return;
        }

        $notification = EmailNotification::firstOrCreate([
            'type' => EmailNotification::TYPE_WELCOME_USER,
            'user_id' => $user->id,
        ]);

        if ($notification->sent_at) {
            return;
        }

        $this->send($user, new WelcomeUserMail($user->refresh()), $notification);
    }

    public function sendCardPurchasePaid(Order $order): void
    {
        $user = $order->user()->first();

        if (! $user || ! $user->email) {
            return;
        }

        $notification = EmailNotification::firstOrCreate([
            'type' => EmailNotification::TYPE_CARD_PURCHASE_PAID,
            'order_id' => $order->id,
        ], [
            'user_id' => $user->id,
            'metadata' => [
                'order_number' => $order->number,
                'quantity' => $order->quantity,
                'amount' => $order->amount,
                'currency' => $order->currency,
            ],
        ]);

        if ($notification->sent_at) {
            return;
        }

        $this->send($user, new CardPurchasePaidMail($user, $order->refresh()), $notification);
    }

    public function sendCardSentToPrint(Card $card, PrintJob $printJob): void
    {
        $user = $card->user()->first();
        $order = $printJob->order()->first();

        if (! $user || ! $user->email) {
            return;
        }

        $notification = EmailNotification::create([
            'user_id' => $user->id,
            'card_id' => $card->id,
            'order_id' => $order?->id,
            'type' => EmailNotification::TYPE_CARD_SENT_TO_PRINT,
            'metadata' => [
                'order_number' => $order?->number,
                'print_job_number' => $printJob->number,
                'card_name' => $card->name,
            ],
        ]);

        $this->send(
            $user,
            new CardSentToPrintMail(
                $user,
                $card->refresh(),
                $printJob->refresh(),
                $order?->refresh(),
                $this->printEvidenceFiles($card)
            ),
            $notification
        );
    }

    public function sendPasswordChanged(User $user): void
    {
        if (! $user->email) {
            return;
        }

        $notification = EmailNotification::create([
            'user_id' => $user->id,
            'type' => EmailNotification::TYPE_PASSWORD_CHANGED,
        ]);

        $this->send($user, new PasswordChangedMail($user->refresh()), $notification);
    }

    public function sendVisitMilestoneIfNeeded(AnalyticsEvent $event): void
    {
        if ($event->event !== 'page_view' || ! $event->link_page_id) {
            return;
        }

        $views = AnalyticsEvent::where('link_page_id', $event->link_page_id)
            ->where('event', 'page_view')
            ->count();

        if (! in_array($views, [10, 50, 100], true)) {
            return;
        }

        $linkPage = LinkPage::with(['user', 'card'])->find($event->link_page_id);

        if (! $linkPage || ! $linkPage->user || ! $linkPage->user->email) {
            return;
        }

        $notification = EmailNotification::firstOrCreate([
            'type' => EmailNotification::TYPE_VIRTUAL_CARD_VISIT_MILESTONE,
            'link_page_id' => $linkPage->id,
            'milestone' => $views,
        ], [
            'user_id' => $linkPage->user_id,
            'card_id' => $linkPage->card_id,
            'metadata' => [
                'visits' => $views,
            ],
        ]);

        if ($notification->sent_at) {
            return;
        }

        $this->send(
            $linkPage->user,
            new VirtualCardVisitMilestoneMail($linkPage->user, $linkPage, $linkPage->card, $views),
            $notification
        );
    }

    /**
     * @return array<int, array{name: string, path: string, label: string}>
     */
    private function printEvidenceFiles(Card $card): array
    {
        $media = $card->getMedia(Card::MEDIA_PRINT_FILES);

        return collect(['front' => 'frente', 'back' => 'reverso'])
            ->map(function (string $label, string $side) use ($media): ?array {
                $file = $media
                    ->sortByDesc('id')
                    ->first(fn (Media $item) => $item->getCustomProperty('side') === $side);

                if (! $file || ! is_file($file->getPath())) {
                    return null;
                }

                return [
                    'name' => "tarjeta-{$label}.".pathinfo($file->file_name, PATHINFO_EXTENSION),
                    'path' => $file->getPath(),
                    'label' => $label,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function send(User $user, mixed $mailable, EmailNotification $notification): void
    {
        try {
            Mail::to($user->email)->send($mailable);

            $notification->update([
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
