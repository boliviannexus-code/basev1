<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE spaces DROP CONSTRAINT IF EXISTS spaces_status_check');
        DB::statement("ALTER TABLE spaces ADD CONSTRAINT spaces_status_check CHECK (status IN ('draft', 'completed', 'needs_corrections', 'in_review', 'approved', 'active', 'inactive'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE spaces DROP CONSTRAINT IF EXISTS spaces_status_check');
        DB::statement("ALTER TABLE spaces ADD CONSTRAINT spaces_status_check CHECK (status IN ('draft', 'completed', 'needs_corrections', 'approved', 'active', 'inactive'))");
    }
};
