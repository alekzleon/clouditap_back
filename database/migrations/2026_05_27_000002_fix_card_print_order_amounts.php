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
            ->where('amount', 9900)
            ->where('currency', 'mxn')
            ->update([
                'amount' => (int) env('CARD_PRINT_AMOUNT_CENTS', 64900),
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
            ->where('amount', 64900)
            ->where('currency', 'mxn')
            ->update([
                'amount' => 9900,
                'updated_at' => now(),
            ]);
    }
};
