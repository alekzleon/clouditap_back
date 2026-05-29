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
        Schema::table('link_pages', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique('link_pages_user_id_unique');
        });

        Schema::table('link_pages', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreignId('card_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->string('status')->default('active')->after('banners');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('link_pages', function (Blueprint $table) {
            $table->dropForeign(['card_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn(['card_id', 'status']);
            $table->unique('user_id');
        });

        Schema::table('link_pages', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
