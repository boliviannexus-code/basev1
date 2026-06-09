<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('stays')
            ->join('check_in_groups', 'check_in_groups.id', '=', 'stays.check_in_group_id')
            ->select([
                'stays.id',
                'stays.company_id',
                'stays.space_id',
                'stays.space_room_id',
                'stays.room_bed_unit_id',
                'stays.check_out_date',
                'check_in_groups.code',
            ])
            ->chunkById(200, function ($stays): void {
                foreach ($stays as $stay) {
                    $query = DB::table('availability_statuses')
                        ->whereNull('deleted_at')
                        ->where('company_id', $stay->company_id)
                        ->where('space_id', $stay->space_id)
                        ->where('date', $stay->check_out_date)
                        ->where('status', 'occupied')
                        ->where('source', 'check_in')
                        ->where('notes', 'Check-in '.$stay->code);

                    filled($stay->space_room_id)
                        ? $query->where('space_room_id', $stay->space_room_id)
                        : $query->whereNull('space_room_id');

                    filled($stay->room_bed_unit_id)
                        ? $query->where('room_bed_unit_id', $stay->room_bed_unit_id)
                        : $query->whereNull('room_bed_unit_id');

                    $query->update([
                        'deleted_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'stays.id', 'id');
    }

    public function down(): void
    {
        //
    }
};
