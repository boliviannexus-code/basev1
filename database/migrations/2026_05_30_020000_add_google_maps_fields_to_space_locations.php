<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('space_locations', function (Blueprint $table): void {
            $table->string('address_text')->nullable()->after('zone_or_neighborhood');
            $table->string('reference_text', 500)->nullable()->after('reference');
            $table->string('google_place_id')->nullable()->after('longitude');

            $table->index(['company_id', 'google_place_id']);
        });
    }

    public function down(): void
    {
        Schema::table('space_locations', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'google_place_id']);
            $table->dropColumn(['address_text', 'reference_text', 'google_place_id']);
        });
    }
};
