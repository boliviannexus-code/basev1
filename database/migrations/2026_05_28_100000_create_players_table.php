<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table): void {
            $table->id();
            $table->string('ci');
            $table->string('ci_normalized')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('internal_code')->nullable();
            $table->date('birth_date');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
            $table->index('internal_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
