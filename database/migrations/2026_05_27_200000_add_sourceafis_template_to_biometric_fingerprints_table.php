<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_fingerprints', function (Blueprint $table): void {
            $table->longText('template_data')->nullable()->after('sample_image');
            $table->string('template_format')->nullable()->after('template_data');
        });
    }

    public function down(): void
    {
        Schema::table('biometric_fingerprints', function (Blueprint $table): void {
            $table->dropColumn(['template_data', 'template_format']);
        });
    }
};
