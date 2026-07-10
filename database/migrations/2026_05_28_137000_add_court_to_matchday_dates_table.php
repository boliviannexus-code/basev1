<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('matchday_dates', 'court_id')) {
            Schema::table('matchday_dates', function (Blueprint $table): void {
                $table->foreignId('court_id')->nullable()->after('matchday_id')->constrained()->restrictOnDelete();
            });
        }

        DB::statement('DROP INDEX IF EXISTS matchday_dates_matchday_date_unique');
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS matchday_dates_matchday_date_court_unique
             ON matchday_dates (matchday_id, date, court_id)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS matchday_dates_matchday_date_court_unique');
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS matchday_dates_matchday_date_unique
             ON matchday_dates (matchday_id, date)
             WHERE deleted_at IS NULL'
        );

        if (Schema::hasColumn('matchday_dates', 'court_id')) {
            Schema::table('matchday_dates', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('court_id');
            });
        }
    }
};
