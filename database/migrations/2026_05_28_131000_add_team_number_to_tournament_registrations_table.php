<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table): void {
            $table->unsignedInteger('team_number')->nullable()->after('team_id');
        });

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

        DB::statement('UPDATE tournament_registrations SET team_number = 1 WHERE team_number IS NULL');
        DB::statement('ALTER TABLE tournament_registrations ALTER COLUMN team_number SET NOT NULL');

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX tournament_registrations_tournament_team_number_unique
            ON tournament_registrations (tournament_id, team_number)
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tournament_registrations_tournament_team_number_unique');

        Schema::table('tournament_registrations', function (Blueprint $table): void {
            $table->dropColumn('team_number');
        });
    }
};
