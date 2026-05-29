<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_team_players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('tournament_registration_id')->constrained('tournament_registrations')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('team_player_id')->constrained('team_players')->restrictOnDelete();
            $table->string('status', 30)->default('enabled');
            $table->timestamp('enabled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'tournament_id', 'team_id', 'status'], 'tournament_team_players_context_index');
            $table->index(['player_id', 'status']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX tournament_team_players_active_tournament_team_player_unique
             ON tournament_team_players (tournament_id, team_id, player_id)
             WHERE status = 'enabled' AND deleted_at IS NULL"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS tournament_team_players_active_tournament_team_player_unique');
        Schema::dropIfExists('tournament_team_players');
    }
};
