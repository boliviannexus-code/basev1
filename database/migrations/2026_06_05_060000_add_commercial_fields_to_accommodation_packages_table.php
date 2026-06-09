<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accommodation_packages', function (Blueprint $table): void {
            $table->json('badges')->nullable()->after('short_description');
            $table->string('price_display_text')->nullable()->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('accommodation_packages', function (Blueprint $table): void {
            $table->dropColumn([
                'badges',
                'price_display_text',
            ]);
        });
    }
};
