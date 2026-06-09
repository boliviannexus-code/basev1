<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            $table->date('birth_date')->nullable()->after('birth_country_id');
            $table->index(['company_id', 'birth_date']);
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'birth_date']);
            $table->dropColumn('birth_date');
        });
    }
};
