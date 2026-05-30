<?php

namespace App\Services;

use App\Models\Card;
use App\Models\CouponRedemption;
use App\Models\Order;
use App\Models\UserCardCredit;
use Illuminate\Support\Facades\DB;
use Stripe\PaymentIntent;

class CardPurchasePaymentService
{
    /**
     * @return array{order: Order, credits: UserCardCredit, just_marked_paid: bool}
     */
    public function markOrderAsPaid(Order $order): array
    {
        $result = DB::transaction(function () use ($order): array {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $justMarkedPaid = false;

            if ($lockedOrder->status !== 'paid') {
                $lockedOrder->update([
                    'status' => 'paid',
                    'paid_at' => $lockedOrder->paid_at ?? now(),
                ]);

                $credits = $this->cardCreditsForUser($lockedOrder->user_id);
                $credits->increment('purchased', $lockedOrder->quantity);
                $this->recordCouponRedemption($lockedOrder);
                $justMarkedPaid = true;
            } else {
                $credits = $this->cardCreditsForUser($lockedOrder->user_id);
            }

            return [
                'order' => $lockedOrder->refresh(),
                'credits' => $credits->refresh(),
                'just_marked_paid' => $justMarkedPaid,
            ];
        });

        if ($result['just_marked_paid']) {
            app(TransactionalEmailService::class)->sendCardPurchasePaid($result['order']);
        }

        return $result;
    }

    public function markOrderAsPaymentFailed(Order $order): Order
    {
        return DB::transaction(function () use ($order): Order {
            $lockedOrder = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status !== 'paid') {
                $lockedOrder->update([
                    'status' => 'payment_failed',
                ]);
            }

            return $lockedOrder->refresh();
        });
    }

    public function markPaymentIntentAsSucceeded(PaymentIntent $paymentIntent): ?array
    {
        $order = $this->orderForPaymentIntent($paymentIntent);

        if (! $order) {
            return null;
        }

        $order->update([
            'stripe_payment_intent_id' => $paymentIntent->id,
            'payment_status' => $paymentIntent->status,
        ]);

        return $this->markOrderAsPaid($order);
    }

    public function markPaymentIntentAsFailed(PaymentIntent $paymentIntent): ?Order
    {
        $order = $this->orderForPaymentIntent($paymentIntent);

        if (! $order) {
            return null;
        }

        $order->update([
            'stripe_payment_intent_id' => $paymentIntent->id,
            'payment_status' => $paymentIntent->status,
        ]);

        return $this->markOrderAsPaymentFailed($order);
    }

    private function orderForPaymentIntent(PaymentIntent $paymentIntent): ?Order
    {
        $metadataOrderId = $paymentIntent->metadata['order_id'] ?? null;

        return Order::where(function ($query) use ($paymentIntent, $metadataOrderId): void {
            $query->where('stripe_payment_intent_id', $paymentIntent->id);

            if ($metadataOrderId) {
                $query->orWhereKey($metadataOrderId);
            }
        })
            ->where('type', Order::TYPE_CARD_PURCHASE)
            ->first();
    }

    private function cardCreditsForUser(int $userId): UserCardCredit
    {
        return UserCardCredit::firstOrCreate(
            ['user_id' => $userId],
            [
                'purchased' => Card::where('user_id', $userId)->count(),
                'used' => Card::where('user_id', $userId)->count(),
            ]
        );
    }

    private function recordCouponRedemption(Order $order): void
    {
        if (! $order->coupon_id || $order->discount_amount <= 0) {
            return;
        }

        CouponRedemption::firstOrCreate(
            ['order_id' => $order->id],
            [
                'coupon_id' => $order->coupon_id,
                'user_id' => $order->user_id,
                'discount_amount' => $order->discount_amount,
            ]
        );
    }
}
