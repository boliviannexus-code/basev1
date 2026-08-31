<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('match_control_items', fn (Blueprint $table) => $table->decimal('absence_cost', 12, 2)->default(0)->after('label'));
        Schema::table('match_reports', fn (Blueprint $table) => $table->json('control_item_costs')->nullable()->after('control_items'));
    }
    public function down(): void
    {
        Schema::table('match_reports', fn (Blueprint $table) => $table->dropColumn('control_item_costs'));
        Schema::table('match_control_items', fn (Blueprint $table) => $table->dropColumn('absence_cost'));
    }
};
