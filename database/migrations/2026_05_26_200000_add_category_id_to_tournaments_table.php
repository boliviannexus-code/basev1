<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table): void {
            if (! Schema::hasColumn('tournaments', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('division_id')->constrained('division_categories')->restrictOnDelete();
            }
        });

        $this->clearDuplicateCategoryAssignments();

        Schema::table('tournaments', function (Blueprint $table): void {
            if ($this->constraintExists('tournaments_company_season_division_name_unique')) {
                $table->dropUnique('tournaments_company_season_division_name_unique');
            }

            if ($this->constraintExists('tournaments_company_id_season_id_division_id_name_unique')) {
                $table->dropUnique('tournaments_company_id_season_id_division_id_name_unique');
            }

            if ($this->constraintExists('tournaments_company_season_division_category_name_unique')) {
                $table->dropUnique('tournaments_company_season_division_category_name_unique');
            }

            if (! $this->constraintExists('tournaments_company_season_category_unique')) {
                $table->unique(['company_id', 'season_id', 'category_id'], 'tournaments_company_season_category_unique');
            }

            if (! $this->indexExists('tournaments_context_category_status_active_index')) {
                $table->index(['company_id', 'season_id', 'division_id', 'category_id', 'status', 'is_active'], 'tournaments_context_category_status_active_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table): void {
            if ($this->constraintExists('tournaments_company_season_division_category_name_unique')) {
                $table->dropUnique('tournaments_company_season_division_category_name_unique');
            }

            if ($this->indexExists('tournaments_context_category_status_active_index')) {
                $table->dropIndex('tournaments_context_category_status_active_index');
            }

            if (! $this->constraintExists('tournaments_company_season_division_name_unique')) {
                $table->unique(['company_id', 'season_id', 'division_id', 'name'], 'tournaments_company_season_division_name_unique');
            }
        });

        Schema::table('tournaments', function (Blueprint $table): void {
            if (Schema::hasColumn('tournaments', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
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

    private function clearDuplicateCategoryAssignments(): void
    {
        DB::table('tournaments')
            ->select('company_id', 'season_id', 'category_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as total'))
            ->whereNotNull('category_id')
            ->groupBy('company_id', 'season_id', 'category_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->each(function (object $duplicate): void {
                DB::table('tournaments')
                    ->where('company_id', $duplicate->company_id)
                    ->where('season_id', $duplicate->season_id)
                    ->where('category_id', $duplicate->category_id)
                    ->where('id', '!=', $duplicate->keep_id)
                    ->update([
                        'category_id' => null,
                        'updated_at' => now(),
                    ]);
            });
    }
};
