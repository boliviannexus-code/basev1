<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_register_expenses', function (Blueprint $table): void {
            $table->foreignId('extra_charge_category_id')
                ->nullable()
                ->after('user_id')
                ->constrained('extra_charge_categories')
                ->nullOnDelete();
        });

        Schema::table('space_cash_expenses', function (Blueprint $table): void {
            $table->foreignId('extra_charge_category_id')
                ->nullable()
                ->after('user_id')
                ->constrained('extra_charge_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('space_cash_expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('extra_charge_category_id');
        });

        Schema::table('cash_register_expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('extra_charge_category_id');
        });
    }
};
