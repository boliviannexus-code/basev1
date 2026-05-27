<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tournament_registrations', 'division_id')) {
            Schema::table('tournament_registrations', function (Blueprint $table): void {
                $table->foreignId('division_id')->nullable()->after('tournament_id')->constrained('divisions')->restrictOnDelete();
            });
        }

        DB::statement(
            'UPDATE tournament_registrations
             SET division_id = tournaments.division_id
             FROM tournaments
             WHERE tournament_registrations.tournament_id = tournaments.id
             AND tournament_registrations.division_id IS NULL'
        );

        $this->softDeleteDuplicateDivisionRegistrations();

        DB::statement('ALTER TABLE tournament_registrations ALTER COLUMN division_id SET NOT NULL');

        Schema::table('tournament_registrations', function (Blueprint $table): void {
            if ($this->constraintExists('tournament_registrations_tournament_id_team_id_unique')) {
                $table->dropUnique('tournament_registrations_tournament_id_team_id_unique');
            }
        });

        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS tournament_registrations_division_team_active_unique ON tournament_registrations (division_id, team_id) WHERE deleted_at IS NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS tournament_registrations_company_division_status_index ON tournament_registrations (company_id, division_id, status)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tournament_registrations_division_team_active_unique');
        DB::statement('DROP INDEX IF EXISTS tournament_registrations_company_division_status_index');

        Schema::table('tournament_registrations', function (Blueprint $table): void {
            if (! $this->constraintExists('tournament_registrations_tournament_id_team_id_unique')) {
                $table->unique(['tournament_id', 'team_id']);
            }

            $table->dropConstrainedForeignId('division_id');
        });
    }

    private function constraintExists(string $name): bool
    {
        return (bool) DB::table('pg_constraint')
            ->where('conname', $name)
            ->exists();
    }

    private function softDeleteDuplicateDivisionRegistrations(): void
    {
        DB::table('tournament_registrations')
            ->select('division_id', 'team_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->whereNull('deleted_at')
            ->whereNotNull('division_id')
            ->groupBy('division_id', 'team_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $duplicate): void {
                DB::table('tournament_registrations')
                    ->where('division_id', $duplicate->division_id)
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
