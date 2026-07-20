<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_attendances', function (Blueprint $table): void {
            $table->foreignId('tournament_registration_id')->nullable()->after('team_id')->constrained('tournament_registrations')->cascadeOnDelete();
            $table->index(['meeting_id', 'tournament_registration_id'], 'meeting_attendance_registration_index');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_attendances', function (Blueprint $table): void {
            $table->dropIndex('meeting_attendance_registration_index');
            $table->dropConstrainedForeignId('tournament_registration_id');
        });
    }
};
