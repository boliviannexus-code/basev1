<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('legacy_references', function (Blueprint $table): void {
            $table->foreignId('company_id')->nullable()->after('batch_id')->constrained('companies')->cascadeOnDelete();
        });

        DB::statement(
            'UPDATE legacy_references
             SET company_id = legacy_import_batches.company_id
             FROM legacy_import_batches
             WHERE legacy_references.batch_id = legacy_import_batches.id
               AND legacy_references.company_id IS NULL'
        );

        Schema::table('legacy_references', function (Blueprint $table): void {
            $table->dropUnique('legacy_references_source_target_unique');
            $table->unique(['source_system', 'company_id', 'source_table', 'source_id', 'target_table'], 'legacy_references_company_source_target_unique');
        });
    }

    public function down(): void
    {
        Schema::table('legacy_references', function (Blueprint $table): void {
            $table->dropUnique('legacy_references_company_source_target_unique');
            $table->unique(['source_system', 'source_table', 'source_id', 'target_table'], 'legacy_references_source_target_unique');
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
