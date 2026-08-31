<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_account_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_matchday_id')->constrained('matchdays')->cascadeOnDelete();
            $table->foreignId('source_match_report_id')->constrained('match_reports')->cascadeOnDelete();
            $table->string('concept_key', 120);
            $table->string('description');
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('unit_amount', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->foreignId('applied_matchday_id')->nullable()->constrained('matchdays')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
            $table->unique(['source_match_report_id', 'team_id', 'concept_key'], 'team_account_source_concept_unique');
            $table->index(['team_id', 'tournament_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('team_account_charges'); }
};
