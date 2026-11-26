<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('cash_registers')->where('status', 'open')->exists() || DB::table('space_cash_registers')->where('status', 'open')->exists()) {
            throw new RuntimeException('Cierra todas las cajas de ventas y espacios antes de unificarlas.');
        }

        Schema::table('space_cash_registers', function (Blueprint $table): void {
            $table->foreignId('cash_register_id')->nullable()->unique()->constrained('cash_registers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('space_cash_registers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cash_register_id');
        });
    }
};
