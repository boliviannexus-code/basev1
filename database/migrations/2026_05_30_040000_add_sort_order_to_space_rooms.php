<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('space_rooms', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->default(0)->after('status');
            $table->index(['company_id', 'space_id', 'sort_order']);
        });

        DB::statement(<<<'SQL'
            UPDATE space_rooms
            SET sort_order = ordered.position
            FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY company_id, space_id ORDER BY id) AS position
                FROM space_rooms
            ) AS ordered
            WHERE ordered.id = space_rooms.id
        SQL);
    }

    public function down(): void
    {
        Schema::table('space_rooms', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'space_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
