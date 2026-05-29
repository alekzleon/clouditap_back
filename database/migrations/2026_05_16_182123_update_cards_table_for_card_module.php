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
        Schema::table('cards', function (Blueprint $table) {
            $table->renameColumn('nfc_uid', 'nfc_id');
            $table->dropColumn(['qr_code_path', 'activated_at', 'deactivated_at']);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->json('design_data')->nullable()->after('nfc_id');
            $table->string('design_file')->nullable()->after('design_data');
            $table->string('qr_url')->nullable()->after('design_file');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['design_data', 'design_file', 'qr_url']);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->renameColumn('nfc_id', 'nfc_uid');
            $table->string('qr_code_path')->nullable()->after('nfc_uid');
            $table->timestamp('activated_at')->nullable()->after('qr_code_path');
            $table->timestamp('deactivated_at')->nullable()->after('activated_at');
        });
    }
};
