<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE availability_statuses DROP CONSTRAINT IF EXISTS availability_statuses_source_check');
        DB::statement("ALTER TABLE availability_statuses ADD CONSTRAINT availability_statuses_source_check CHECK (source IN ('manual', 'reservation', 'system', 'check_in'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE availability_statuses DROP CONSTRAINT IF EXISTS availability_statuses_source_check');
        DB::statement("ALTER TABLE availability_statuses ADD CONSTRAINT availability_statuses_source_check CHECK (source IN ('manual', 'reservation', 'system'))");
    }
};
