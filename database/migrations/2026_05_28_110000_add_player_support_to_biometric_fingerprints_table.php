<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('biometric_fingerprints', function (Blueprint $table): void {
            $table->foreignId('player_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['player_id', 'finger_position', 'is_active'], 'biometric_fingerprints_player_finger_active_index');
        });

        Schema::table('biometric_fingerprints', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('biometric_fingerprints', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->dropForeign(['player_id']);
            $table->dropIndex('biometric_fingerprints_player_finger_active_index');
            $table->dropColumn('player_id');
        });
    }
};
