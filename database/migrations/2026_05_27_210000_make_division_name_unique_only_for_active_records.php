<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE divisions DROP CONSTRAINT IF EXISTS divisions_company_id_name_unique');
        DB::statement('DROP INDEX IF EXISTS divisions_company_name_active_unique');
        DB::statement('CREATE UNIQUE INDEX divisions_company_name_active_unique ON divisions (company_id, LOWER(name)) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS divisions_company_name_active_unique');
        DB::statement('ALTER TABLE divisions ADD CONSTRAINT divisions_company_id_name_unique UNIQUE (company_id, name)');
    }
};
