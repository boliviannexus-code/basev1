<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_details', function (Blueprint $table): void {
            $table->foreignId('extra_charge_category_id')
                ->nullable()
                ->after('presentation_id')
                ->constrained('extra_charge_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sale_details', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('extra_charge_category_id');
        });
    }
};
