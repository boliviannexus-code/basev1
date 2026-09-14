<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_control_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('key', 80);
            $table->string('label', 120);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'key']);
            $table->index(['company_id', 'is_active', 'sort_order']);
        });

        Schema::table('match_reports', function (Blueprint $table): void {
            $table->json('control_items')->nullable()->after('away_paid_court_fee');
        });

        $now = now();
        DB::table('companies')->orderBy('id')->pluck('id')->each(function (int $companyId) use ($now): void {
            DB::table('match_control_items')->insert([
                'company_id' => $companyId,
                'key' => Str::slug('Trajo balon', '_'),
                'label' => 'Trajo balon',
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('match_reports', function (Blueprint $table): void {
            $table->dropColumn('control_items');
        });

        Schema::dropIfExists('match_control_items');
    }
};
