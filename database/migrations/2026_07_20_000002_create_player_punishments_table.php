<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_punishments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('red_card_article_id')->constrained()->restrictOnDelete();
            $table->string('duration_type', 20);
            $table->unsignedSmallInteger('duration_value')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('reason');
            $table->string('status', 30)->default('active');
            $table->text('lift_reason')->nullable();
            $table->timestamp('lift_requested_at')->nullable();
            $table->foreignId('lift_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('lift_review_note')->nullable();
            $table->timestamp('lift_reviewed_at')->nullable();
            $table->foreignId('lift_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['player_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_punishments');
    }
};
