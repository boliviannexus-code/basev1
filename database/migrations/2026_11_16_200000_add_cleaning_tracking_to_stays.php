<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stays', function (Blueprint $table): void {
            $table->timestamp('cleaning_required_at')->nullable();
            $table->timestamp('cleaned_at')->nullable();
            $table->foreignId('cleaned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['company_id', 'status', 'cleaned_at']);
        });
    }

    public function down(): void
    {
        Schema::table('stays', function (Blueprint $table): void {
            $table->dropForeign(['cleaned_by']);
            $table->dropIndex(['company_id', 'status', 'cleaned_at']);
            $table->dropColumn(['cleaning_required_at', 'cleaned_at', 'cleaned_by']);
        });
    }
};
