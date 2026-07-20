<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('logo_path')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->string('hero_eyebrow')->nullable();
            $table->string('hero_title')->nullable();
            $table->text('hero_subtitle')->nullable();
            $table->boolean('popup_enabled')->default(false);
            $table->string('popup_title')->nullable();
            $table->text('popup_body')->nullable();
            $table->string('popup_cta_label')->nullable();
            $table->string('popup_cta_url')->nullable();
            $table->string('popup_image_path')->nullable();
            $table->json('featured_tour_ids')->nullable();
            $table->boolean('show_companies')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_settings');
    }
};
