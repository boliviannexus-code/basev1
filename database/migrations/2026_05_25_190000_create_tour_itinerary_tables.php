<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_itinerary_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_number');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('accommodation')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tour_id', 'day_number']);
        });

        Schema::create('tour_itinerary_stops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_itinerary_day_id')->constrained('tour_itinerary_days')->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained('activity_types')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->time('start_time')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('included')->default(true);
            $table->timestamps();

            $table->index(['tour_itinerary_day_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_itinerary_stops');
        Schema::dropIfExists('tour_itinerary_days');
    }
};
