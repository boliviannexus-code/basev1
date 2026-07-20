<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->boolean('pets_allowed')->default(false)->after('includes_transport');
            $table->boolean('bookings_enabled')->default(true)->after('booking_deadline_unit');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->dropColumn(['pets_allowed', 'bookings_enabled']);
        });
    }
};
