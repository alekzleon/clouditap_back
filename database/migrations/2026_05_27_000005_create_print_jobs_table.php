<?php

use App\Models\Order;
use App\Models\PrintJob;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->unique();
            $table->string('status')->default('paid');
            $table->unsignedInteger('quantity')->default(1);
            $table->json('shipping_address')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['order_id', 'status']);
        });

        Schema::create('print_job_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('print_job_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('note');
            $table->timestamps();

            $table->index(['print_job_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        $legacyPrintOrders = Order::query()
            ->where('type', Order::TYPE_CARD_PRINT)
            ->whereNotNull('card_id')
            ->get();

        foreach ($legacyPrintOrders as $legacyOrder) {
            PrintJob::firstOrCreate(
                ['card_id' => $legacyOrder->card_id],
                [
                    'user_id' => $legacyOrder->user_id,
                    'order_id' => $this->purchaseOrderIdFor($legacyOrder),
                    'number' => PrintJob::uniqueNumber(),
                    'status' => $legacyOrder->status === 'pending_payment' ? 'paid' : $legacyOrder->status,
                    'quantity' => $legacyOrder->quantity ?: 1,
                    'shipping_address' => $legacyOrder->shipping_address,
                    'paid_at' => $legacyOrder->paid_at ?? now(),
                    'created_at' => $legacyOrder->created_at,
                    'updated_at' => $legacyOrder->updated_at,
                ]
            );
        }

        DB::table('orders')->where('type', Order::TYPE_CARD_PRINT)->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_job_status_logs');
        Schema::dropIfExists('print_jobs');
    }

    private function purchaseOrderIdFor(Order $legacyOrder): ?int
    {
        return Order::query()
            ->where('user_id', $legacyOrder->user_id)
            ->where('type', Order::TYPE_CARD_PURCHASE)
            ->where('status', 'paid')
            ->oldest('paid_at')
            ->value('id');
    }
};
