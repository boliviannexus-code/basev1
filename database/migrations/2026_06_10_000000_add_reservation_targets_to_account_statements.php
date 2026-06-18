<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_statements', function (Blueprint $table): void {
            $table->foreignId('stay_id')->nullable()->change();
        });

        Schema::table('account_statements', function (Blueprint $table): void {
            $table->foreignId('reservation_id')->nullable()->after('stay_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_group_id')->nullable()->after('reservation_id')->constrained('reservation_groups')->cascadeOnDelete();
            $table->index(['company_id', 'reservation_group_id']);
        });

        Schema::table('account_statement_items', function (Blueprint $table): void {
            $table->foreignId('reservation_id')->nullable()->after('stay_id')->constrained()->nullOnDelete();
            $table->foreignId('reservation_group_id')->nullable()->after('reservation_id')->constrained('reservation_groups')->nullOnDelete();
            $table->index(['company_id', 'reservation_group_id']);
        });
    }

    public function down(): void
    {
        Schema::table('account_statement_items', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'reservation_group_id']);
            $table->dropConstrainedForeignId('reservation_group_id');
            $table->dropConstrainedForeignId('reservation_id');
        });

        Schema::table('account_statements', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'reservation_group_id']);
            $table->dropConstrainedForeignId('reservation_group_id');
            $table->dropConstrainedForeignId('reservation_id');
        });

        Schema::table('account_statements', function (Blueprint $table): void {
            $table->foreignId('stay_id')->nullable(false)->change();
        });
    }
};
