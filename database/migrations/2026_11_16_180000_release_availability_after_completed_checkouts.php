<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Match the original check-in marker and protect rooms occupied again.
        DB::table('availability_statuses as availability')
            ->whereNull('availability.deleted_at')
            ->where('availability.status', 'occupied')
            ->where('availability.source', 'check_in')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')->from('stays')
                    ->join('check_in_groups', 'check_in_groups.id', '=', 'stays.check_in_group_id')
                    ->where('stays.status', 'checked_out')
                    ->whereNull('stays.deleted_at')
                    ->whereColumn('stays.company_id', 'availability.company_id')
                    ->whereColumn('stays.space_id', 'availability.space_id')
                    ->whereRaw('stays.space_room_id IS NOT DISTINCT FROM availability.space_room_id')
                    ->whereRaw('stays.room_bed_unit_id IS NOT DISTINCT FROM availability.room_bed_unit_id')
                    ->whereRaw("availability.notes = 'Check-in ' || check_in_groups.code")
                    ->whereRaw('availability.date >= CAST(stays.checked_out_at AS DATE)')
                    ->whereColumn('availability.date', '>=', 'stays.check_in_date')
                    ->whereColumn('availability.date', '<', 'stays.check_out_date');
            })
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('stays as current_stay')
                    ->where('current_stay.status', 'occupied')
                    ->whereNull('current_stay.deleted_at')
                    ->whereColumn('current_stay.company_id', 'availability.company_id')
                    ->whereColumn('current_stay.space_id', 'availability.space_id')
                    ->whereRaw('current_stay.space_room_id IS NOT DISTINCT FROM availability.space_room_id')
                    ->whereColumn('current_stay.check_in_date', '<=', 'availability.date')
                    ->whereColumn('current_stay.check_out_date', '>', 'availability.date');
            })
            ->update(['deleted_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Released dates may have been sold again; do not restore stale markers.
    }
};
