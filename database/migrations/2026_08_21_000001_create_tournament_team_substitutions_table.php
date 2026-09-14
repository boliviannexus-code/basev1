<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_team_substitutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('tournament_registration_id')->constrained('tournament_registrations')->cascadeOnDelete();
            $table->foreignId('outgoing_team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('incoming_team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->unsignedInteger('fixture_matches_updated')->default(0);
            $table->unsignedInteger('standing_adjustments_updated')->default(0);
            $table->unsignedInteger('habilitations_disabled')->default(0);
            $table->unsignedInteger('accreditations_removed')->default(0);
            $table->timestamp('substituted_at');
            $table->timestamps();

            $table->index(['company_id', 'tournament_id'], 'tournament_team_substitutions_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_team_substitutions');
    }
};
