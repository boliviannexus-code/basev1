<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_registers') && ! Schema::hasColumn('cash_registers', 'company_id')) {
            Schema::table('cash_registers', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });

            if (DB::connection()->getDriverName() === 'pgsql' && Schema::hasTable('point_of_sales')) {
                DB::statement('UPDATE cash_registers SET company_id = point_of_sales.company_id FROM point_of_sales WHERE cash_registers.point_of_sale_id = point_of_sales.id');
            }
        }

        if (Schema::hasTable('cash_register_expenses') && ! Schema::hasColumn('cash_register_expenses', 'company_id')) {
            Schema::table('cash_register_expenses', function (Blueprint $table): void {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });

            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('UPDATE cash_register_expenses SET company_id = cash_registers.company_id FROM cash_registers WHERE cash_register_expenses.cash_register_id = cash_registers.id');
            }
        }

        if (! Schema::hasTable('cash_register_user_sequences')) {
            Schema::create('cash_register_user_sequences', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('receipt_prefix');
                $table->unsignedInteger('receipt_next_number')->default(1);
                $table->unsignedTinyInteger('receipt_digits')->default(6);
                $table->timestamps();
                $table->unique(['company_id', 'user_id']);
            });
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            foreach ([
                'cash_registers',
                'cash_register_expenses',
                'sales',
                'cash_register_lodging_payments',
            ] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'point_of_sale_id')) {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN point_of_sale_id DROP NOT NULL");
                }
            }

            foreach (['cash_registers', 'sales'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'branch_id')) {
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN branch_id DROP NOT NULL");
                }
            }

            if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'warehouse_id')) {
                DB::statement('ALTER TABLE sales ALTER COLUMN warehouse_id DROP NOT NULL');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_user_sequences');
    }
};
