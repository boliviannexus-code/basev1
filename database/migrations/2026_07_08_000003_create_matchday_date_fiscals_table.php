<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matchday_date_fiscals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matchday_date_id')->constrained('matchday_dates')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->restrictOnDelete();
            $table->time('start_time');
            $table->time('end_time');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['matchday_date_id', 'start_time', 'end_time'], 'matchday_date_fiscals_schedule_index');
            $table->index(['company_id', 'team_id'], 'matchday_date_fiscals_team_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matchday_date_fiscals');
    }
};
