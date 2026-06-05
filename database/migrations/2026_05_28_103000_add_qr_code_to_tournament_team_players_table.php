<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_team_players', function (Blueprint $table): void {
            $table->string('qr_code_path')->nullable()->after('enabled_at');
            $table->unsignedInteger('qr_code_size')->nullable()->after('qr_code_path');
        });
    }

    public function down(): void
    {
        Schema::table('tournament_team_players', function (Blueprint $table): void {
            $table->dropColumn(['qr_code_path', 'qr_code_size']);
        });
    }
};
