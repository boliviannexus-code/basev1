<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tour_availabilities')
            ->where('status', 'no_operation')
            ->update(['status' => 'closed']);

        $this->setDefault('closed');
    }

    public function down(): void
    {
        $this->setDefault('no_operation');
    }

    private function setDefault(string $status): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement("ALTER TABLE tour_availabilities ALTER status SET DEFAULT '{$status}'"),
            'pgsql' => DB::statement("ALTER TABLE tour_availabilities ALTER COLUMN status SET DEFAULT '{$status}'"),
            default => null,
        };
    }
};
