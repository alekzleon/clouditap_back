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
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->foreignId('link_page_id')->nullable()->after('profile_id')->constrained()->nullOnDelete();
            $table->string('target_type')->nullable()->after('event');
            $table->string('target_id')->nullable()->after('target_type');
            $table->string('visitor_id')->nullable()->after('target_id');

            $table->index(['link_page_id', 'occurred_at']);
            $table->index(['event', 'occurred_at']);
            $table->index(['target_type', 'target_id']);
            $table->index(['visitor_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropIndex(['link_page_id', 'occurred_at']);
            $table->dropIndex(['event', 'occurred_at']);
            $table->dropIndex(['target_type', 'target_id']);
            $table->dropIndex(['visitor_id', 'occurred_at']);
            $table->dropConstrainedForeignId('link_page_id');
            $table->dropColumn(['target_type', 'target_id', 'visitor_id']);
        });
    }
};
