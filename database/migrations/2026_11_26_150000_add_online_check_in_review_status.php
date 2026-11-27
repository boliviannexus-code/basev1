<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('online_check_in_submissions', function (Blueprint $table): void {
            $table->string('review_status', 20)->default('pending');
        });
        DB::table('online_check_in_submissions')->whereNotNull('reviewed_at')->update(['review_status' => 'reviewed']);
    }

    public function down(): void
    {
        Schema::table('online_check_in_submissions', function (Blueprint $table): void {
            $table->dropColumn('review_status');
        });
    }
};
