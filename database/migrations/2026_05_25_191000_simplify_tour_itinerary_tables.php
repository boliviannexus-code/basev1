<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_itinerary_days', function (Blueprint $table): void {
            $table->dropColumn(['accommodation', 'notes']);
        });

        Schema::table('tour_itinerary_stops', function (Blueprint $table): void {
            $table->dropColumn(['duration_minutes', 'description', 'latitude', 'longitude', 'included']);
        });
    }

    public function down(): void
    {
        Schema::table('tour_itinerary_days', function (Blueprint $table): void {
            $table->string('accommodation')->nullable();
            $table->text('notes')->nullable();
        });

        Schema::table('tour_itinerary_stops', function (Blueprint $table): void {
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('included')->default(true);
        });
    }
};
