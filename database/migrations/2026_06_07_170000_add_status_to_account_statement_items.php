<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_statement_items', function (Blueprint $table): void {
            $table->string('status')->default('active')->after('source');
            $table->index(['account_statement_id', 'status']);
        });

        DB::statement("ALTER TABLE account_statement_items ADD CONSTRAINT account_statement_items_status_check CHECK (status IN ('active', 'cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE account_statement_items DROP CONSTRAINT IF EXISTS account_statement_items_status_check');

        Schema::table('account_statement_items', function (Blueprint $table): void {
            $table->dropIndex(['account_statement_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
