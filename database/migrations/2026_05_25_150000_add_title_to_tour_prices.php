<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tour_prices', 'title')) {
            Schema::table('tour_prices', function (Blueprint $table): void {
                $table->string('title')->nullable()->after('tour_id');
            });
        }

        if (! Schema::hasColumn('tour_availability_prices', 'title')) {
            Schema::table('tour_availability_prices', function (Blueprint $table): void {
                $table->string('title')->nullable()->after('tour_price_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tour_availability_prices', 'title')) {
            Schema::table('tour_availability_prices', function (Blueprint $table): void {
                $table->dropColumn('title');
            });
        }

        if (Schema::hasColumn('tour_prices', 'title')) {
            Schema::table('tour_prices', function (Blueprint $table): void {
                $table->dropColumn('title');
            });
        }
    }
};
