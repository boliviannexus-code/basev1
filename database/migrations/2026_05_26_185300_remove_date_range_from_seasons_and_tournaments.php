<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table): void {
            if (Schema::hasColumn('seasons', 'starts_at')) {
                $table->dropColumn('starts_at');
            }

            if (Schema::hasColumn('seasons', 'ends_at')) {
                $table->dropColumn('ends_at');
            }
        });

        Schema::table('tournaments', function (Blueprint $table): void {
            if (Schema::hasColumn('tournaments', 'starts_at')) {
                $table->dropColumn('starts_at');
            }

            if (Schema::hasColumn('tournaments', 'ends_at')) {
                $table->dropColumn('ends_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table): void {
            if (! Schema::hasColumn('seasons', 'starts_at')) {
                $table->date('starts_at')->nullable();
            }

            if (! Schema::hasColumn('seasons', 'ends_at')) {
                $table->date('ends_at')->nullable();
            }
        });

        Schema::table('tournaments', function (Blueprint $table): void {
            if (! Schema::hasColumn('tournaments', 'starts_at')) {
                $table->date('starts_at')->nullable();
            }

            if (! Schema::hasColumn('tournaments', 'ends_at')) {
                $table->date('ends_at')->nullable();
            }
        });
    }
};
