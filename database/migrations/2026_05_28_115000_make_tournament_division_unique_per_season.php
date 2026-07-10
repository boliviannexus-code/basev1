<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->softDeleteDuplicateTournaments();

        DB::statement(
            'CREATE UNIQUE INDEX tournaments_company_season_division_unique
             ON tournaments (company_id, season_id, division_id)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tournaments_company_season_division_unique');
    }

    private function softDeleteDuplicateTournaments(): void
    {
        DB::statement(
            "WITH ranked_tournaments AS (
                SELECT
                    tournaments.id,
                    ROW_NUMBER() OVER (
                        PARTITION BY tournaments.company_id, tournaments.season_id, tournaments.division_id
                        ORDER BY
                            COUNT(tournament_registrations.id) DESC,
                            CASE tournaments.status WHEN 'active' THEN 0 WHEN 'planned' THEN 1 ELSE 2 END,
                            tournaments.id ASC
                    ) AS duplicate_rank
                FROM tournaments
                LEFT JOIN tournament_registrations
                    ON tournament_registrations.tournament_id = tournaments.id
                    AND tournament_registrations.deleted_at IS NULL
                WHERE tournaments.deleted_at IS NULL
                GROUP BY tournaments.id
            )
            UPDATE tournaments
            SET deleted_at = NOW(),
                updated_at = NOW(),
                is_active = FALSE
            FROM ranked_tournaments
            WHERE tournaments.id = ranked_tournaments.id
              AND ranked_tournaments.duplicate_rank > 1"
        );
    }
};
