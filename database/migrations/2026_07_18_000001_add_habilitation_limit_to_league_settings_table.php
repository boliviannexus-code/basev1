<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('max_enabled_players_per_team_category')
                ->default(0)
                ->after('insurance_fee');
        });
    }

    public function down(): void
    {
        Schema::table('league_settings', function (Blueprint $table): void {
            $table->dropColumn('max_enabled_players_per_team_category');
        });
    }
};
