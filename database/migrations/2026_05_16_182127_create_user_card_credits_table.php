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
        Schema::create('user_card_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('purchased')->default(0);
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();
        });

        $users = DB::table('users')->select('id')->get();

        foreach ($users as $user) {
            $used = DB::table('cards')->where('user_id', $user->id)->count();

            DB::table('user_card_credits')->insert([
                'user_id' => $user->id,
                'purchased' => $used,
                'used' => $used,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_card_credits');
    }
};
