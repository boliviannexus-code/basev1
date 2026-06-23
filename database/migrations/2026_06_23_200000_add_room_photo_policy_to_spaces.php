<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spaces', function (Blueprint $table): void {
            $table->boolean('room_photos_skipped')->default(false)->after('photos_skipped');
        });

        DB::table('spaces')
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('space_rooms')
                ->whereColumn('space_rooms.space_id', 'spaces.id')
                ->whereNull('space_rooms.deleted_at'))
            ->whereNotExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('space_rooms')
                ->whereColumn('space_rooms.space_id', 'spaces.id')
                ->whereNull('space_rooms.deleted_at')
                ->where('space_rooms.photos_skipped', false))
            ->update(['room_photos_skipped' => true]);
    }

    public function down(): void
    {
        Schema::table('spaces', function (Blueprint $table): void {
            $table->dropColumn('room_photos_skipped');
        });
    }
};
