<?php

use App\Support\CountryCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('iso_code', 2);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'iso_code']);
            $table->index(['company_id', 'is_active', 'is_featured']);
            $table->index(['company_id', 'sort_order']);
        });

        DB::table('companies')
            ->orderBy('id')
            ->chunkById(100, function ($companies): void {
                foreach ($companies as $company) {
                    CountryCatalog::seedForCompany((int) $company->id);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
