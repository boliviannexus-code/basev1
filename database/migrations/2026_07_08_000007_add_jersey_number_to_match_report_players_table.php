<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('match_report_players', function (Blueprint $table): void {
            $table->unsignedSmallInteger('jersey_number')->nullable()->after('team_side');
        });
    }

    public function down(): void
    {
        Schema::table('match_report_players', function (Blueprint $table): void {
            $table->dropColumn('jersey_number');
        });
    }
};
