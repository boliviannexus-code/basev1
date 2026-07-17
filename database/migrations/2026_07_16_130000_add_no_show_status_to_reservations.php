<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_status_check');
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_status_check CHECK (status IN ('pending_data', 'pending_payment', 'payment_under_review', 'confirmed', 'checked_in', 'rejected', 'cancelled', 'expired', 'no_show'))");

        DB::statement('ALTER TABLE reservation_groups DROP CONSTRAINT IF EXISTS reservation_groups_status_check');
        DB::statement("ALTER TABLE reservation_groups ADD CONSTRAINT reservation_groups_status_check CHECK (status IN ('pending_payment', 'payment_under_review', 'confirmed', 'checked_in', 'cancelled', 'no_show'))");
    }

    public function down(): void
    {
        DB::table('reservations')->where('status', 'no_show')->update(['status' => 'cancelled']);
        DB::table('reservation_groups')->where('status', 'no_show')->update(['status' => 'cancelled']);

        DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_status_check');
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_status_check CHECK (status IN ('pending_data', 'pending_payment', 'payment_under_review', 'confirmed', 'checked_in', 'rejected', 'cancelled', 'expired'))");

        DB::statement('ALTER TABLE reservation_groups DROP CONSTRAINT IF EXISTS reservation_groups_status_check');
        DB::statement("ALTER TABLE reservation_groups ADD CONSTRAINT reservation_groups_status_check CHECK (status IN ('pending_payment', 'payment_under_review', 'confirmed', 'checked_in', 'cancelled'))");
    }
};
