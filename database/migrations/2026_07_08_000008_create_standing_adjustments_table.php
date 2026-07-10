<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standing_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained('tournaments')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('division_categories')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('series', 30);
            $table->integer('points_adjustment');
            $table->text('reason');
            $table->timestamps();

            $table->index(['company_id', 'tournament_id', 'category_id', 'series'], 'standing_adjustments_scope_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standing_adjustments');
    }
};
