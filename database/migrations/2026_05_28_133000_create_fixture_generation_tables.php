<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixture_generations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('division_categories')->cascadeOnDelete();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('active');
            $table->json('config');
            $table->unsignedInteger('matches_count')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'tournament_id', 'category_id', 'status'], 'fixture_generations_scope_index');
        });

        Schema::create('fixture_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('fixture_generation_id')->constrained('fixture_generations')->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->foreignId('category_id')->constrained('division_categories')->cascadeOnDelete();
            $table->string('phase', 30);
            $table->string('stage', 40);
            $table->unsignedInteger('stage_order')->default(1);
            $table->string('series', 30)->nullable();
            $table->unsignedInteger('round_number')->nullable();
            $table->unsignedInteger('match_number');
            $table->unsignedInteger('tie_number')->nullable();
            $table->unsignedInteger('leg_number')->default(1);
            $table->foreignId('home_registration_id')->nullable()->constrained('tournament_registrations')->nullOnDelete();
            $table->foreignId('away_registration_id')->nullable()->constrained('tournament_registrations')->nullOnDelete();
            $table->foreignId('home_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('away_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('home_seed')->nullable();
            $table->string('away_seed')->nullable();
            $table->string('status', 30)->default('pending_schedule');
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['fixture_generation_id', 'phase', 'stage_order'], 'fixture_matches_generation_phase_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixture_matches');
        Schema::dropIfExists('fixture_generations');
    }
};
