<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('source_system')->default('mejillones');
            $table->string('source_path')->nullable();
            $table->string('source_hash', 128)->nullable();
            $table->foreignId('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('mode', 30);
            $table->string('status', 30)->default('running');
            $table->json('summary')->nullable();
            $table->string('report_path')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['source_system', 'company_id', 'status']);
        });

        Schema::create('legacy_import_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('legacy_import_batches')->cascadeOnDelete();
            $table->string('source_table');
            $table->string('source_id')->nullable();
            $table->string('target_table')->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('action', 40);
            $table->string('status', 40);
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index(['batch_id', 'status']);
            $table->index(['source_table', 'source_id']);
            $table->index(['target_table', 'target_id']);
        });

        Schema::create('legacy_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('legacy_import_batches')->nullOnDelete();
            $table->string('source_system')->default('mejillones');
            $table->string('source_table');
            $table->string('source_id');
            $table->string('target_table');
            $table->unsignedBigInteger('target_id');
            $table->string('fingerprint', 128)->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_table', 'source_id', 'target_table'], 'legacy_references_source_target_unique');
            $table->index(['target_table', 'target_id']);
        });

        Schema::create('legacy_series', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('division_category_id')->nullable()->constrained('division_categories')->nullOnDelete();
            $table->unsignedBigInteger('legacy_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'legacy_id']);
            $table->index(['company_id', 'division_category_id']);
        });

        Schema::create('legacy_transfer_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignId('from_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('to_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('tournament_id')->nullable()->constrained('tournaments')->nullOnDelete();
            $table->string('source_table');
            $table->unsignedBigInteger('legacy_id');
            $table->string('event_type', 40);
            $table->string('status', 40)->default('pending_review');
            $table->json('payload')->nullable();
            $table->timestamp('event_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'source_table', 'legacy_id']);
            $table->index(['company_id', 'event_type', 'status']);
        });

        DB::statement("ALTER TABLE legacy_import_batches ADD CONSTRAINT legacy_import_batches_mode_check CHECK (mode IN ('dry_run', 'import', 'analysis'))");
        DB::statement("ALTER TABLE legacy_import_batches ADD CONSTRAINT legacy_import_batches_status_check CHECK (status IN ('running', 'completed', 'failed', 'rolled_back'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_transfer_events');
        Schema::dropIfExists('legacy_series');
        Schema::dropIfExists('legacy_references');
        Schema::dropIfExists('legacy_import_logs');
        Schema::dropIfExists('legacy_import_batches');
    }
};
