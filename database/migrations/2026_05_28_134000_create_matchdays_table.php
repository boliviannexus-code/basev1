<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matchdays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('name')->nullable();
            $table->string('status')->default('draft');
            $table->date('scheduled_date')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['company_id', 'season_id', 'status']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX matchdays_company_season_number_unique
             ON matchdays (company_id, season_id, number)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS matchdays_company_season_number_unique');
        Schema::dropIfExists('matchdays');
    }
};
