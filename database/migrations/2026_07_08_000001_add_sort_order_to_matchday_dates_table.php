<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matchday_dates', function (Blueprint $table): void {
            $table->unsignedInteger('sort_order')->nullable()->after('date');
            $table->index(['matchday_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('matchday_dates', function (Blueprint $table): void {
            $table->dropIndex(['matchday_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
