<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_reports', function (Blueprint $table): void {
            $table->unsignedSmallInteger('home_score')->default(0)->after('away_paid_court_fee');
            $table->unsignedSmallInteger('away_score')->default(0)->after('home_score');
            $table->unsignedSmallInteger('home_points')->default(0)->after('away_score');
            $table->unsignedSmallInteger('away_points')->default(0)->after('home_points');
            $table->string('wo_side', 20)->nullable()->after('away_points');
            $table->string('wo_reason', 60)->nullable()->after('wo_side');
        });

        Schema::create('match_report_players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('match_report_id')->constrained('match_reports')->cascadeOnDelete();
            $table->foreignId('tournament_team_player_id')->constrained('tournament_team_players')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('team_side', 10);
            $table->unsignedSmallInteger('goals')->default(0);
            $table->unsignedSmallInteger('yellow_cards')->default(0);
            $table->unsignedSmallInteger('red_cards')->default(0);
            $table->timestamps();

            $table->unique(['match_report_id', 'tournament_team_player_id'], 'match_report_player_unique');
            $table->index(['match_report_id', 'team_side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_report_players');

        Schema::table('match_reports', function (Blueprint $table): void {
            $table->dropColumn([
                'home_score',
                'away_score',
                'home_points',
                'away_points',
                'wo_side',
                'wo_reason',
            ]);
        });
    }
};
