<?php

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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_provider')->default('stripe')->after('currency');
            $table->string('payment_status')->nullable()->after('stripe_payment_intent_id');

            $table->index(['payment_provider', 'payment_status']);
        });

        DB::table('orders')
            ->where('type', 'card_purchase')
            ->whereNull('payment_status')
            ->update([
                'payment_status' => DB::raw('status'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_provider', 'payment_status']);
            $table->dropColumn([
                'payment_provider',
                'payment_status',
            ]);
        });
    }
};
