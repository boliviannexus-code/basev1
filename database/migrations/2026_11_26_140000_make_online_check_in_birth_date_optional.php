<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_check_in_submissions', function (Blueprint $table): void {
            $table->date('birth_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep the column nullable so rolling back cannot invalidate newer submissions.
    }
};
