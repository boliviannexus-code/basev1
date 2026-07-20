<?php

use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->string('name_match_key')->nullable()->after('name_normalized');
        });

        Team::query()
            ->withTrashed()
            ->get(['id', 'name', 'name_normalized', 'name_match_key'])
            ->each(function (Team $team): void {
                $name = Team::formatName((string) $team->name);

                $team->forceFill([
                    'name' => $name,
                    'name_normalized' => Team::normalizeName($name),
                    'name_match_key' => Team::matchKey($name),
                ])->save();
            });

        DB::statement(
            'CREATE INDEX teams_company_name_match_key_index
             ON teams (company_id, name_match_key)'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS teams_company_name_match_key_index');

        Schema::table('teams', function (Blueprint $table): void {
            $table->dropColumn('name_match_key');
        });
    }
};
