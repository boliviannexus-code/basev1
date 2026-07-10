<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_category_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('division_category_id')->constrained('division_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tournament_id', 'division_category_id'], 'tournament_category_assignments_unique');
        });

        DB::statement(
            'INSERT INTO tournament_category_assignments (tournament_id, division_category_id, created_at, updated_at)
             SELECT id, category_id, NOW(), NOW()
             FROM tournaments
             WHERE category_id IS NOT NULL
             ON CONFLICT DO NOTHING'
        );

        Schema::table('tournament_registrations', function (Blueprint $table): void {
            $table->foreignId('category_id')
                ->nullable()
                ->after('division_id')
                ->constrained('division_categories')
                ->nullOnDelete();
        });

        DB::statement(
            'UPDATE tournament_registrations
             SET category_id = tournaments.category_id
             FROM tournaments
             WHERE tournament_registrations.tournament_id = tournaments.id
               AND tournament_registrations.category_id IS NULL'
        );

        Schema::table('tournaments', function (Blueprint $table): void {
            if ($this->constraintExists('tournaments_company_season_category_unique')) {
                $table->dropUnique('tournaments_company_season_category_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournament_registrations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('tournament_category_assignments');
    }

    private function constraintExists(string $name): bool
    {
        return (bool) DB::table('pg_constraint')
            ->where('conname', $name)
            ->exists();
    }
};
