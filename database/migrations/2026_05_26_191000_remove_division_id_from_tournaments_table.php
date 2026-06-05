<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tournaments', 'division_id')) {
            DB::statement('ALTER TABLE tournaments DROP CONSTRAINT IF EXISTS tournaments_company_season_division_name_unique');

            if ($this->indexExists('tournaments_company_id_season_id_division_id_status_is_active_index')) {
                Schema::table('tournaments', function (Blueprint $table): void {
                    $table->dropIndex('tournaments_company_id_season_id_division_id_status_is_active_index');
                });
            }

            Schema::table('tournaments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('division_id');
            });
        }

        $this->deduplicateTournamentNames();

        Schema::table('tournaments', function (Blueprint $table): void {
            if (! $this->constraintExists('tournaments_company_id_season_id_name_unique')) {
                $table->unique(['company_id', 'season_id', 'name'], 'tournaments_company_id_season_id_name_unique');
            }

            if (! $this->indexExists('tournaments_company_id_season_id_status_is_active_index')) {
                $table->index(['company_id', 'season_id', 'status', 'is_active'], 'tournaments_company_id_season_id_status_is_active_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table): void {
            if ($this->constraintExists('tournaments_company_id_season_id_name_unique')) {
                $table->dropUnique('tournaments_company_id_season_id_name_unique');
            }

            if ($this->indexExists('tournaments_company_id_season_id_status_is_active_index')) {
                $table->dropIndex('tournaments_company_id_season_id_status_is_active_index');
            }
        });

        if (! Schema::hasColumn('tournaments', 'division_id')) {
            Schema::table('tournaments', function (Blueprint $table): void {
                $table->foreignId('division_id')->nullable()->constrained('divisions')->restrictOnDelete();
            });
        }
    }

    private function deduplicateTournamentNames(): void
    {
        DB::table('tournaments')
            ->select('company_id', 'season_id', 'name', DB::raw('COUNT(*) as total'))
            ->groupBy('company_id', 'season_id', 'name')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $duplicate): void {
                DB::table('tournaments')
                    ->where('company_id', $duplicate->company_id)
                    ->where('season_id', $duplicate->season_id)
                    ->where('name', $duplicate->name)
                    ->orderBy('id')
                    ->pluck('id')
                    ->skip(1)
                    ->each(function (int $id) use ($duplicate): void {
                        DB::table('tournaments')
                            ->where('id', $id)
                            ->update(['name' => $duplicate->name.' #'.$id]);
                    });
            });
    }

    private function constraintExists(string $name): bool
    {
        return (bool) DB::table('pg_constraint')
            ->where('conname', $name)
            ->exists();
    }

    private function indexExists(string $name): bool
    {
        return (bool) DB::table('pg_indexes')
            ->where('tablename', 'tournaments')
            ->where('indexname', $name)
            ->exists();
    }
};
