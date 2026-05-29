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
        Schema::table('user_card_credits', function (Blueprint $table) {
            $table->unsignedInteger('purchased')->default(0)->change();
        });

        $credits = DB::table('user_card_credits')->select('user_id', 'purchased', 'used')->get();

        foreach ($credits as $credit) {
            $paidCards = (int) DB::table('orders')
                ->where('user_id', $credit->user_id)
                ->where('type', 'card_purchase')
                ->where('status', 'paid')
                ->sum('quantity');

            $earnedCredits = max((int) $credit->used, $paidCards);

            if ((int) $credit->purchased > $earnedCredits) {
                DB::table('user_card_credits')
                    ->where('user_id', $credit->user_id)
                    ->update([
                        'purchased' => $earnedCredits,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_card_credits', function (Blueprint $table) {
            $table->unsignedInteger('purchased')->default(1)->change();
        });
    }
};
