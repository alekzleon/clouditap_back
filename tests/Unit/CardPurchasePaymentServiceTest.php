<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\User;
use App\Services\CardPurchasePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardPurchasePaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_a_card_purchase_order_as_paid_only_once(): void
    {
        $user = User::factory()->create([
            'slug' => 'test-user',
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'card_id' => null,
            'number' => Order::uniqueNumber(),
            'type' => Order::TYPE_CARD_PURCHASE,
            'status' => 'pending_payment',
            'quantity' => 2,
            'amount' => 129800,
            'currency' => 'mxn',
            'stripe_payment_intent_id' => 'pi_test_123',
        ]);

        $service = app(CardPurchasePaymentService::class);

        $firstResult = $service->markOrderAsPaid($order);
        $secondResult = $service->markOrderAsPaid($order->refresh());

        $this->assertSame('paid', $firstResult['order']->status);
        $this->assertNotNull($firstResult['order']->paid_at);
        $this->assertSame(2, $firstResult['credits']->purchased);
        $this->assertSame(2, $secondResult['credits']->purchased);
    }
}
