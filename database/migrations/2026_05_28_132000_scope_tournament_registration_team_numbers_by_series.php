<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS tournament_registrations_tournament_team_number_unique');

        DB::statement(<<<'SQL'
            WITH ordered_registrations AS (
                SELECT id,
                       ROW_NUMBER() OVER (
                           PARTITION BY tournament_id, category_id, series
                           ORDER BY created_at ASC, id ASC
                       ) AS generated_team_number
                FROM tournament_registrations
                WHERE deleted_at IS NULL
            )
            UPDATE tournament_registrations
            SET team_number = ordered_registrations.generated_team_number
            FROM ordered_registrations
            WHERE tournament_registrations.id = ordered_registrations.id
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX tournament_registrations_series_team_number_unique
            ON tournament_registrations (tournament_id, category_id, series, team_number)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tournament_registrations_series_team_number_unique');

        DB::statement(<<<'SQL'
            WITH ordered_registrations AS (
                SELECT id,
                       ROW_NUMBER() OVER (
                           PARTITION BY tournament_id
                           ORDER BY created_at ASC, id ASC
                       ) AS generated_team_number
                FROM tournament_registrations
                WHERE deleted_at IS NULL
            )
            UPDATE tournament_registrations
            SET team_number = ordered_registrations.generated_team_number
            FROM ordered_registrations
            WHERE tournament_registrations.id = ordered_registrations.id
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX tournament_registrations_tournament_team_number_unique
            ON tournament_registrations (tournament_id, team_number)
            WHERE deleted_at IS NULL
        SQL);
    }
};
