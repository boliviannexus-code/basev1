<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropIndex('players_company_name_index');
            $table->dropUnique('players_company_ci_normalized_unique');
        });

        DB::statement(
            "UPDATE players
             SET internal_code = 'N' || LPAD((id + 99)::text, 5, '0')"
        );

        Schema::table('players', function (Blueprint $table): void {
            $table->unique('ci_normalized', 'players_ci_normalized_unique');
            $table->unique('internal_code', 'players_internal_code_unique');
            $table->index(['last_name', 'first_name'], 'players_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropIndex('players_name_index');
            $table->dropUnique('players_internal_code_unique');
            $table->dropUnique('players_ci_normalized_unique');
            $table->unique(['company_id', 'ci_normalized'], 'players_company_ci_normalized_unique');
            $table->index(['company_id', 'last_name', 'first_name'], 'players_company_name_index');
        });
    }
};
