<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('divisions', function (Blueprint $table): void {
            if (! Schema::hasColumn('divisions', 'min_age')) {
                $table->unsignedTinyInteger('min_age')->default(0);
            }

            if (! Schema::hasColumn('divisions', 'max_age')) {
                $table->unsignedTinyInteger('max_age')->default(120);
            }
        });

        if (! Schema::hasColumn('tournaments', 'division_id')) {
            Schema::table('tournaments', function (Blueprint $table): void {
                $table->foreignId('division_id')->nullable()->constrained('divisions')->restrictOnDelete();
            });

            Company::query()
                ->whereHas('tournaments')
                ->each(function (Company $company): void {
                    $divisionId = DB::table('divisions')
                        ->where('company_id', $company->id)
                        ->where('name', 'Division general')
                        ->value('id');

                    if (! $divisionId) {
                        $divisionId = DB::table('divisions')->insertGetId([
                            'company_id' => $company->id,
                            'name' => 'Division general',
                            'min_age' => 0,
                            'max_age' => 120,
                            'description' => 'Division creada automaticamente para torneos existentes.',
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('tournaments')
                        ->where('company_id', $company->id)
                        ->whereNull('division_id')
                        ->update(['division_id' => $divisionId]);
                });

            DB::statement('ALTER TABLE tournaments ALTER COLUMN division_id SET NOT NULL');
        }

        Schema::table('tournaments', function (Blueprint $table): void {
            if ($this->constraintExists('tournaments_company_id_season_id_name_unique')) {
                $table->dropUnique('tournaments_company_id_season_id_name_unique');
            }

            if ($this->indexExists('tournaments_company_id_season_id_status_is_active_index')) {
                $table->dropIndex('tournaments_company_id_season_id_status_is_active_index');
            }

            if (! $this->constraintExists('tournaments_company_season_division_name_unique')) {
                $table->unique(['company_id', 'season_id', 'division_id', 'name'], 'tournaments_company_season_division_name_unique');
            }

            if (! $this->indexExists('tournaments_company_season_division_status_active_index')) {
                $table->index(['company_id', 'season_id', 'division_id', 'status', 'is_active'], 'tournaments_company_season_division_status_active_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table): void {
            if ($this->constraintExists('tournaments_company_season_division_name_unique')) {
                $table->dropUnique('tournaments_company_season_division_name_unique');
            }

            if ($this->indexExists('tournaments_company_season_division_status_active_index')) {
                $table->dropIndex('tournaments_company_season_division_status_active_index');
            }

            if (! $this->constraintExists('tournaments_company_id_season_id_name_unique')) {
                $table->unique(['company_id', 'season_id', 'name'], 'tournaments_company_id_season_id_name_unique');
            }

            if (! $this->indexExists('tournaments_company_id_season_id_status_is_active_index')) {
                $table->index(['company_id', 'season_id', 'status', 'is_active'], 'tournaments_company_id_season_id_status_is_active_index');
            }
        });

        if (Schema::hasColumn('tournaments', 'division_id')) {
            Schema::table('tournaments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('division_id');
            });
        }

        Schema::table('divisions', function (Blueprint $table): void {
            if (Schema::hasColumn('divisions', 'max_age')) {
                $table->dropColumn('max_age');
            }

            if (Schema::hasColumn('divisions', 'min_age')) {
                $table->dropColumn('min_age');
            }
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
