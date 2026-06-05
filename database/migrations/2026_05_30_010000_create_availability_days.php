<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_room_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('status')->default('available');
            $table->timestamps();

            $table->index(['company_id', 'space_id', 'space_room_id']);
            $table->index(['company_id', 'date']);
            $table->index(['status']);
        });

        DB::statement("ALTER TABLE availability_days ADD CONSTRAINT availability_days_status_check CHECK (status IN ('available', 'closed'))");
        DB::statement('CREATE UNIQUE INDEX availability_days_private_unique ON availability_days (company_id, space_id, date) WHERE space_room_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX availability_days_room_unique ON availability_days (company_id, space_room_id, date) WHERE space_room_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_days');
    }
};
