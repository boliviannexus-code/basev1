<?php

use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->string('name_normalized')->nullable()->after('name');
        });

        Team::query()
            ->withTrashed()
            ->get(['id', 'name'])
            ->each(fn (Team $team) => $team->forceFill([
                'name_normalized' => Team::normalizeName($team->name),
            ])->save());

        Schema::table('teams', function (Blueprint $table): void {
            $table->string('name_normalized')->nullable(false)->change();
            $table->unique(['company_id', 'name_normalized']);
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'name_normalized']);
            $table->dropColumn('name_normalized');
        });
    }
};
