<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE reservation_groups DROP CONSTRAINT IF EXISTS reservation_groups_status_check');
        DB::statement("ALTER TABLE reservation_groups ADD CONSTRAINT reservation_groups_status_check CHECK (status IN ('pending_payment', 'payment_under_review', 'confirmed', 'checked_in', 'cancelled'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reservation_groups DROP CONSTRAINT IF EXISTS reservation_groups_status_check');
        DB::statement("ALTER TABLE reservation_groups ADD CONSTRAINT reservation_groups_status_check CHECK (status IN ('pending_payment', 'confirmed', 'checked_in', 'cancelled'))");
    }
};
