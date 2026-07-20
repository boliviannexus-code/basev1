<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixture_matches', function (Blueprint $table): void {
            $table->foreignId('matchday_date_id')->nullable()->after('away_seed')->constrained('matchday_dates')->nullOnDelete();
            $table->time('scheduled_time')->nullable()->after('matchday_date_id');

            $table->index(['matchday_date_id', 'scheduled_time']);
        });
    }

    public function down(): void
    {
        Schema::table('fixture_matches', function (Blueprint $table): void {
            $table->dropIndex(['matchday_date_id', 'scheduled_time']);
            $table->dropConstrainedForeignId('matchday_date_id');
            $table->dropColumn('scheduled_time');
        });
    }
};
