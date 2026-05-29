<?php

use App\Models\Player;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('players')
            ->whereNull('internal_code')
            ->orWhere('internal_code', '')
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(500, function ($players): void {
                foreach ($players as $player) {
                    DB::table('players')
                        ->where('id', $player->id)
                        ->update(['internal_code' => Player::internalCodeForId((int) $player->id)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('players')
            ->where('internal_code', 'like', 'Nex%')
            ->orderBy('id')
            ->select(['id', 'internal_code'])
            ->chunkById(500, function ($players): void {
                foreach ($players as $player) {
                    if ($player->internal_code !== Player::internalCodeForId((int) $player->id)) {
                        continue;
                    }

                    DB::table('players')
                        ->where('id', $player->id)
                        ->update(['internal_code' => null]);
                }
            });
    }
};
