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
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained('companies')
                ->nullOnDelete();
        });

        DB::statement(
            'UPDATE players
             SET company_id = (
                 SELECT team_players.company_id
                 FROM team_players
                 WHERE team_players.player_id = players.id
                   AND team_players.deleted_at IS NULL
                 ORDER BY team_players.id ASC
                 LIMIT 1
             )
             WHERE company_id IS NULL'
        );

        DB::statement(
            'UPDATE players
             SET company_id = (
                 SELECT tournament_team_players.company_id
                 FROM tournament_team_players
                 WHERE tournament_team_players.player_id = players.id
                   AND tournament_team_players.deleted_at IS NULL
                 ORDER BY tournament_team_players.id ASC
                 LIMIT 1
             )
             WHERE company_id IS NULL'
        );

        Schema::table('players', function (Blueprint $table): void {
            $table->dropUnique('players_ci_normalized_unique');
            $table->unique(['company_id', 'ci_normalized'], 'players_company_ci_normalized_unique');
            $table->index(['company_id', 'last_name', 'first_name'], 'players_company_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropIndex('players_company_name_index');
            $table->dropUnique('players_company_ci_normalized_unique');
            $table->unique('ci_normalized', 'players_ci_normalized_unique');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
