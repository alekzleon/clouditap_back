<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('slug');
            $table->string('referral_code')->nullable()->unique()->after('avatar_path');
            $table->json('shipping_address')->nullable()->after('referral_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['referral_code']);
            $table->dropColumn(['avatar_path', 'referral_code', 'shipping_address']);
        });
    }
};
