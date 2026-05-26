<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_booking_id')->nullable()->constrained('tour_bookings')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['tour_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_reviews');
    }
};
