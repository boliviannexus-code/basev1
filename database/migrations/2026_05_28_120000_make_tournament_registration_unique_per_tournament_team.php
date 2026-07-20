<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->softDeleteDuplicateTournamentRegistrations();

        DB::statement('DROP INDEX IF EXISTS tournament_registrations_division_team_active_unique');
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS tournament_registrations_tournament_team_active_unique
             ON tournament_registrations (tournament_id, team_id)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tournament_registrations_tournament_team_active_unique');
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS tournament_registrations_division_team_active_unique
             ON tournament_registrations (division_id, team_id)
             WHERE deleted_at IS NULL'
        );
    }

    private function softDeleteDuplicateTournamentRegistrations(): void
    {
        DB::table('tournament_registrations')
            ->select('tournament_id', 'team_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->whereNull('deleted_at')
            ->groupBy('tournament_id', 'team_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $duplicate): void {
                DB::table('tournament_registrations')
                    ->where('tournament_id', $duplicate->tournament_id)
                    ->where('team_id', $duplicate->team_id)
                    ->where('id', '!=', $duplicate->keep_id)
                    ->whereNull('deleted_at')
                    ->update([
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]);
            });
    }
};
