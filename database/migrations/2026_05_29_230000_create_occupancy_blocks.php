<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occupancy_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_room_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status')->default('active');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'space_id', 'space_room_id']);
            $table->index(['start_date', 'end_date']);
            $table->index(['type', 'status']);
        });

        DB::statement("ALTER TABLE occupancy_blocks ADD CONSTRAINT occupancy_blocks_type_check CHECK (type IN ('manual_block', 'maintenance', 'owner_use', 'unavailable'))");
        DB::statement("ALTER TABLE occupancy_blocks ADD CONSTRAINT occupancy_blocks_status_check CHECK (status IN ('active', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('occupancy_blocks');
    }
};
