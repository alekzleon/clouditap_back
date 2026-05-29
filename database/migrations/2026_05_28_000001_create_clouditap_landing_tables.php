<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('superadmin', 'web');
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('client', 'web');

        Schema::create('tap_hero_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('subtitle')->nullable();
            $table->string('media_type')->default('image');
            $table->string('media_path')->nullable();
            $table->string('mobile_media_path')->nullable();
            $table->string('button_one_text')->nullable();
            $table->string('button_one_url')->nullable();
            $table->boolean('button_one_active')->default(false);
            $table->string('button_two_text')->nullable();
            $table->string('button_two_url')->nullable();
            $table->boolean('button_two_active')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tap_card_designs', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('category')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tap_pricing_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('price_label');
            $table->string('badge')->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->string('button_text')->nullable();
            $table->string('button_url')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tap_reviews', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('business')->nullable();
            $table->text('comment');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->string('photo_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tap_faqs', function (Blueprint $table): void {
            $table->id();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tap_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('logo_path')->nullable();
            $table->text('footer_text')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('email')->nullable();
            $table->string('instagram_url')->nullable();
            $table->string('facebook_url')->nullable();
            $table->string('tiktok_url')->nullable();
            $table->string('privacy_url')->nullable();
            $table->string('terms_url')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tap_settings');
        Schema::dropIfExists('tap_faqs');
        Schema::dropIfExists('tap_reviews');
        Schema::dropIfExists('tap_pricing_plans');
        Schema::dropIfExists('tap_card_designs');
        Schema::dropIfExists('tap_hero_settings');
    }
};
