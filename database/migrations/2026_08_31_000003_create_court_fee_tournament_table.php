<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_fee_tournament', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('court_fee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['court_fee_id', 'tournament_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_fee_tournament');
    }
};
