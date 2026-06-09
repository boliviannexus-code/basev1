<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('availability_statuses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_room_id')->nullable()->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('status')->default('available');
            $table->string('source')->default('manual');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'space_id', 'space_room_id']);
            $table->index(['company_id', 'date']);
            $table->index(['status']);
            $table->index(['source']);
        });

        DB::statement("ALTER TABLE availability_statuses ADD CONSTRAINT availability_statuses_status_check CHECK (status IN ('available', 'closed', 'occupied', 'reserved'))");
        DB::statement("ALTER TABLE availability_statuses ADD CONSTRAINT availability_statuses_source_check CHECK (source IN ('manual', 'reservation', 'system'))");
        DB::statement('CREATE UNIQUE INDEX availability_statuses_private_unique ON availability_statuses (company_id, space_id, date) WHERE space_room_id IS NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX availability_statuses_room_unique ON availability_statuses (company_id, space_room_id, date) WHERE space_room_id IS NOT NULL AND deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('availability_statuses');
    }
};
