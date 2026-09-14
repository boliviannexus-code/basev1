<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('amount_per_team', 12, 2);
            $table->unsignedSmallInteger('installments');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('extra_charge_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('extra_charge_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('all_teams')->default(false);
            $table->timestamps();
            $table->unique(['extra_charge_id', 'tournament_id', 'team_id'], 'extra_charge_team_assignment_unique');
            $table->index(['extra_charge_id', 'tournament_id', 'all_teams']);
        });

        Schema::create('extra_charge_installments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extra_charge_id')->constrained()->restrictOnDelete();
            $table->foreignId('matchday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('installment_number');
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['extra_charge_id', 'matchday_id', 'tournament_id', 'team_id'], 'extra_charge_matchday_team_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_charge_installments');
        Schema::dropIfExists('extra_charge_assignments');
        Schema::dropIfExists('extra_charges');
    }
};
