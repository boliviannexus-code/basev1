<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_transfer_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->decimal('fee_amount', 10, 2)->nullable();
            $table->unsignedInteger('next_sequence')->default(1);
            $table->timestamps();

            $table->unique('company_id');
        });

        Schema::create('player_transfer_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('division_id')->constrained('divisions')->restrictOnDelete();
            $table->foreignId('from_team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('to_team_id')->constrained('teams')->restrictOnDelete();
            $table->foreignId('from_team_player_id')->nullable()->constrained('team_players')->nullOnDelete();
            $table->foreignId('to_team_player_id')->nullable()->constrained('team_players')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code');
            $table->unsignedInteger('sequence');
            $table->string('status', 30)->default('pending');
            $table->decimal('fee_amount', 10, 2);
            $table->decimal('collected_amount', 10, 2)->nullable();
            $table->text('requested_note');
            $table->text('review_notes')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'sequence']);
            $table->index(['company_id', 'status', 'created_at']);
            $table->index(['company_id', 'division_id', 'player_id']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX player_transfer_pending_unique
             ON player_transfer_requests (company_id, division_id, player_id, to_team_id)
             WHERE status = 'pending' AND deleted_at IS NULL"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS player_transfer_pending_unique');
        Schema::dropIfExists('player_transfer_requests');
        Schema::dropIfExists('player_transfer_settings');
    }
};
