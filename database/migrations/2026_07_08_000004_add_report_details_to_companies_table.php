<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->date('foundation_date')->nullable()->after('country');
            $table->string('legal_personality')->nullable()->after('foundation_date');
            $table->text('interest_data')->nullable()->after('legal_personality');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'foundation_date',
                'legal_personality',
                'interest_data',
            ]);
        });
    }
};
