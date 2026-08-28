<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_out_alert_escalations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stay_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('reason');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_out_alert_escalations');
    }
};
