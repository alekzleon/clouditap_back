<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('orders')
            ->where('type', 'card_print')
            ->where('status', 'pending_payment')
            ->update([
                'status' => 'paid',
                'amount' => 0,
                'paid_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('orders')
            ->where('type', 'card_print')
            ->where('status', 'paid')
            ->where('amount', 0)
            ->update([
                'status' => 'pending_payment',
                'amount' => (int) env('CARD_PRINT_AMOUNT_CENTS', 64900),
                'paid_at' => null,
                'updated_at' => now(),
            ]);
    }
};
