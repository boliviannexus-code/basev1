<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('availability_days', function (Blueprint $table): void {
            $table->boolean('is_public_online')->default(true)->after('status');
            $table->index(['company_id', 'is_public_online']);
        });
    }

    public function down(): void
    {
        Schema::table('availability_days', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'is_public_online']);
            $table->dropColumn('is_public_online');
        });
    }
};
