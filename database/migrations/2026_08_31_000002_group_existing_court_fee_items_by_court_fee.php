<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('court_fees') || ! Schema::hasColumn('court_fee_items', 'company_id')) {
            return;
        }

        Schema::create('court_fees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::table('court_fee_items', function (Blueprint $table): void {
            $table->foreignId('court_fee_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        DB::table('court_fee_items')->select('company_id')->distinct()->orderBy('company_id')->each(function (object $row): void {
            $courtFeeId = DB::table('court_fees')->insertGetId([
                'company_id' => $row->company_id,
                'name' => 'Derecho de cancha general',
                'description' => 'Creado automáticamente desde los parámetros existentes.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('court_fee_items')->where('company_id', $row->company_id)->update(['court_fee_id' => $courtFeeId]);
        });

        Schema::table('court_fee_items', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'name']);
            $table->dropColumn('company_id');
            $table->unique(['court_fee_id', 'name']);
        });
    }

    public function down(): void
    {
        // La migración conserva datos existentes y no se revierte automáticamente.
    }
};
