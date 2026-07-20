<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('league_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('max_meeting_permissions_per_team')->default(0)->after('max_enabled_players_per_team_category');
        });

        Schema::table('meeting_attendances', function (Blueprint $table): void {
            $table->boolean('permission_requested')->default(false)->after('marked_by');
            $table->timestamp('permission_requested_at')->nullable()->after('permission_requested');
            $table->foreignId('permission_requested_by')->nullable()->after('permission_requested_at')->constrained('users')->nullOnDelete();
            $table->text('permission_reason')->nullable()->after('permission_requested_by');
            $table->index(['company_id', 'team_id', 'permission_requested'], 'meeting_permissions_team_index');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_attendances', function (Blueprint $table): void {
            $table->dropIndex('meeting_permissions_team_index');
            $table->dropConstrainedForeignId('permission_requested_by');
            $table->dropColumn(['permission_requested', 'permission_requested_at', 'permission_reason']);
        });

        Schema::table('league_settings', function (Blueprint $table): void {
            $table->dropColumn('max_meeting_permissions_per_team');
        });
    }
};
