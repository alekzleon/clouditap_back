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
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('card_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event')->default('profile_view');
            $table->string('source')->default('direct');
            $table->string('device')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('referer')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at')->useCurrent();
            $table->timestamps();

            $table->index(['profile_id', 'occurred_at']);
            $table->index(['card_id', 'occurred_at']);
            $table->index(['source', 'occurred_at']);
            $table->index(['device', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
