<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('occupancy_block_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('capacity');
            $table->decimal('price_per_night', 10, 2);
            $table->decimal('subtotal_amount', 10, 2);
            $table->timestamps();

            $table->unique(['reservation_id', 'space_room_id']);
            $table->index(['space_room_id', 'reservation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_rooms');
    }
};
