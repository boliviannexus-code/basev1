<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_team_players', function (Blueprint $table): void {
            $table->foreignId('enabled_by')->nullable()->after('enabled_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_team_players', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('enabled_by');
        });
    }
};
