<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stays', function (Blueprint $table): void {
            $table->timestamp('checked_out_at')->nullable();
        });

        // Existing checkouts only have the timestamp of their last update.
        DB::table('stays')->where('status', 'checked_out')->update([
            'checked_out_at' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('stays', function (Blueprint $table): void {
            $table->dropColumn('checked_out_at');
        });
    }
};
