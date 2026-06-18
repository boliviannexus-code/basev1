<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('availability_days', function (Blueprint $table): void {
            $table->foreignId('room_bed_unit_id')->nullable()->after('space_room_id')->constrained('room_bed_units')->nullOnDelete();
            $table->index(['company_id', 'room_bed_unit_id']);
        });

        DB::statement('DROP INDEX IF EXISTS availability_days_private_unique');
        DB::statement('DROP INDEX IF EXISTS availability_days_room_unique');
        DB::statement('CREATE UNIQUE INDEX availability_days_private_unique ON availability_days (company_id, space_id, date) WHERE space_room_id IS NULL AND room_bed_unit_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX availability_days_room_unique ON availability_days (company_id, space_room_id, date) WHERE space_room_id IS NOT NULL AND room_bed_unit_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX availability_days_bed_unit_unique ON availability_days (company_id, room_bed_unit_id, date) WHERE room_bed_unit_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS availability_days_bed_unit_unique');
        DB::statement('DROP INDEX IF EXISTS availability_days_private_unique');
        DB::statement('DROP INDEX IF EXISTS availability_days_room_unique');
        DB::statement('CREATE UNIQUE INDEX availability_days_private_unique ON availability_days (company_id, space_id, date) WHERE space_room_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX availability_days_room_unique ON availability_days (company_id, space_room_id, date) WHERE space_room_id IS NOT NULL');

        Schema::table('availability_days', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'room_bed_unit_id']);
            $table->dropConstrainedForeignId('room_bed_unit_id');
        });
    }
};
