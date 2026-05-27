<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('division_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('division_id')->constrained('divisions')->restrictOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'division_id', 'name'], 'division_categories_company_division_name_unique');
            $table->index(['company_id', 'division_id', 'is_active'], 'division_categories_context_index');
        });

        if (Schema::hasTable('tournament_categories')) {
            DB::table('tournament_categories')
                ->join('tournaments', 'tournaments.id', '=', 'tournament_categories.tournament_id')
                ->select([
                    'tournament_categories.company_id',
                    'tournaments.division_id',
                    'tournament_categories.name',
                    'tournament_categories.description',
                    'tournament_categories.is_active',
                    'tournament_categories.created_at',
                    'tournament_categories.updated_at',
                    'tournament_categories.deleted_at',
                ])
                ->orderBy('tournament_categories.id')
                ->get()
                ->each(function (object $category): void {
                    $exists = DB::table('division_categories')
                        ->where('company_id', $category->company_id)
                        ->where('division_id', $category->division_id)
                        ->whereRaw('LOWER(name) = LOWER(?)', [$category->name])
                        ->exists();

                    if ($exists) {
                        return;
                    }

                    DB::table('division_categories')->insert([
                        'company_id' => $category->company_id,
                        'division_id' => $category->division_id,
                        'name' => $category->name,
                        'description' => $category->description,
                        'is_active' => $category->is_active,
                        'created_at' => $category->created_at,
                        'updated_at' => $category->updated_at,
                        'deleted_at' => $category->deleted_at,
                    ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('division_categories');
    }
};
