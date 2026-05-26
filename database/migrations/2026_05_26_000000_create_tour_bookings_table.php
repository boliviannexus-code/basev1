<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->foreignId('tour_availability_id')->nullable()->constrained('tour_availabilities')->nullOnDelete();
            $table->string('booking_code')->unique();
            $table->date('travel_date');
            $table->unsignedInteger('people');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone');
            $table->string('country');
            $table->text('special_requirements')->nullable();
            $table->decimal('unit_price_usd', 10, 2);
            $table->decimal('total_usd', 10, 2);
            $table->string('status')->default('confirmed');
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['tour_id', 'travel_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_bookings');
    }
};
