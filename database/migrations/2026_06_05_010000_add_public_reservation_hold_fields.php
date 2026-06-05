<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            if (! Schema::hasColumn('reservations', 'guest_country')) {
                $table->string('guest_country')->nullable()->after('guest_phone');
            }

            if (! Schema::hasColumn('reservations', 'hold_expires_at')) {
                $table->timestamp('hold_expires_at')->nullable()->after('payment_proof_path')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            if (Schema::hasColumn('reservations', 'hold_expires_at')) {
                $table->dropColumn('hold_expires_at');
            }

            if (Schema::hasColumn('reservations', 'guest_country')) {
                $table->dropColumn('guest_country');
            }
        });
    }
};
