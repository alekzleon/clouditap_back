<?php

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Order::query()
            ->where('type', Order::TYPE_CARD_PRINT)
            ->whereNull('shipping_address')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    $user = User::find($order->user_id);
                    $shippingAddress = $user?->shipping_address
                        ?? $this->latestPaidPurchaseShippingAddress($order);

                    if (! $shippingAddress) {
                        continue;
                    }

                    $order->update([
                        'shipping_address' => $shippingAddress,
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestPaidPurchaseShippingAddress(Order $order): ?array
    {
        $purchaseOrder = Order::query()
            ->where('user_id', $order->user_id)
            ->where('type', Order::TYPE_CARD_PURCHASE)
            ->where('status', 'paid')
            ->whereNotNull('shipping_address')
            ->latest('paid_at')
            ->first();

        return $purchaseOrder?->shipping_address;
    }
};
