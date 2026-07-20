<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('red_card_articles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('number', 50);
            $table->text('detail');
            $table->timestamps();

            $table->unique(['company_id', 'number']);
        });

        Schema::create('red_card_sanctions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fixture_match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_team_player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('red_card_article_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('jersey_number');
            $table->text('action_detail');
            $table->unsignedSmallInteger('suspended_matches');
            $table->timestamps();

            $table->unique(['fixture_match_id', 'tournament_team_player_id'], 'red_card_sanction_player_match_unique');
            $table->index(['company_id', 'fixture_match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('red_card_sanctions');
        Schema::dropIfExists('red_card_articles');
    }
};
