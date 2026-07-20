<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_availabilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->date('date');
            $table->string('status')->default('closed');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('booked_count')->default(0);
            $table->text('restrictions')->nullable();
            $table->timestamps();

            $table->unique(['tour_id', 'date']);
            $table->index(['date', 'status']);
        });

        Schema::create('tour_availability_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_availability_id')->constrained('tour_availabilities')->cascadeOnDelete();
            $table->foreignId('tour_price_id')->nullable()->constrained('tour_prices')->nullOnDelete();
            $table->string('title')->nullable();
            $table->unsignedInteger('min_people');
            $table->unsignedInteger('max_people')->nullable();
            $table->decimal('price_usd', 10, 2);
            $table->timestamps();

            $table->index(['tour_availability_id', 'tour_price_id'], 'tour_availability_price_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_availability_prices');
        Schema::dropIfExists('tour_availabilities');
    }
};
