<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('space_cash_incomes', function (Blueprint $table): void {
            $table->decimal('quantity', 12, 2)->default(1)->after('detail');
        });

        Schema::table('space_cash_expenses', function (Blueprint $table): void {
            $table->decimal('quantity', 12, 2)->default(1)->after('detail');
            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('extra_charge_category_id')
                ->constrained('payment_methods')
                ->nullOnDelete();
        });

        Schema::table('cash_register_expenses', function (Blueprint $table): void {
            $table->decimal('quantity', 12, 2)->default(1)->after('detail');
            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('extra_charge_category_id')
                ->constrained('payment_methods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cash_register_expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn('quantity');
        });

        Schema::table('space_cash_expenses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn('quantity');
        });

        Schema::table('space_cash_incomes', function (Blueprint $table): void {
            $table->dropColumn('quantity');
        });
    }
};
