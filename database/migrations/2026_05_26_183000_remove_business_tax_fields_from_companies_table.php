<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'legal_name')) {
                $table->dropColumn('legal_name');
            }

            if (Schema::hasColumn('companies', 'tax_id')) {
                $table->dropColumn('tax_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'legal_name')) {
                $table->string('legal_name')->nullable();
            }

            if (! Schema::hasColumn('companies', 'tax_id')) {
                $table->string('tax_id')->nullable();
            }
        });
    }
};
