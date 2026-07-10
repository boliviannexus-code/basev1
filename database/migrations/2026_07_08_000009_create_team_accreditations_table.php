<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_accreditations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tournament_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->string('ci', 50);
            $table->string('ci_normalized', 50);
            $table->string('first_name');
            $table->string('last_name');
            $table->string('maternal_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tournament_registration_id', 'slot']);
            $table->index(['company_id', 'tournament_id', 'team_id']);
            $table->index(['company_id', 'ci_normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_accreditations');
    }
};
