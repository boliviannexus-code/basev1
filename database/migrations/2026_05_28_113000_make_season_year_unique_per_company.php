<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table): void {
            $table->dropUnique('seasons_company_id_name_unique');
        });

        DB::statement(
            'CREATE UNIQUE INDEX seasons_company_year_unique
             ON seasons (company_id, year)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS seasons_company_year_unique');

        Schema::table('seasons', function (Blueprint $table): void {
            $table->unique(['company_id', 'name']);
        });
    }
};
