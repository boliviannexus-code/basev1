<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->time('check_out_time')->default('11:00:00');
            $table->unsignedSmallInteger('check_out_alert_snooze_minutes')->default(30);
        });

        Schema::create('check_out_alert_snoozes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stay_id')->constrained()->cascadeOnDelete();
            $table->timestamp('snoozed_until');
            $table->timestamps();
            $table->unique(['user_id', 'stay_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_out_alert_snoozes');
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['check_out_time', 'check_out_alert_snooze_minutes']);
        });
    }
};
