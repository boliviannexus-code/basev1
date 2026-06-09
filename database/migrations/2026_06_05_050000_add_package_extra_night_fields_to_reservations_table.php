<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->unsignedInteger('package_extra_nights')->nullable()->after('extra_people_total');
            $table->decimal('package_extra_nights_total', 10, 2)->nullable()->after('package_extra_nights');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropColumn([
                'package_extra_nights',
                'package_extra_nights_total',
            ]);
        });
    }
};
