<?php

use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Team::query()
            ->withTrashed()
            ->get(['id', 'name'])
            ->each(function (Team $team): void {
                $name = Team::formatName((string) $team->name);

                DB::table('teams')->where('id', $team->id)->update([
                    'name' => $name,
                    'name_normalized' => Team::normalizeName($name),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        //
    }
};
