<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('fixture_match_id')->constrained('fixture_matches')->cascadeOnDelete();
            $table->string('referee_1')->nullable();
            $table->string('referee_2')->nullable();
            $table->string('referee_3')->nullable();
            $table->boolean('home_brought_ball')->default(false);
            $table->boolean('away_brought_ball')->default(false);
            $table->boolean('home_present')->default(false);
            $table->boolean('away_present')->default(false);
            $table->boolean('home_paid_court_fee')->default(false);
            $table->boolean('away_paid_court_fee')->default(false);
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('fixture_match_id');
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_reports');
    }
};
