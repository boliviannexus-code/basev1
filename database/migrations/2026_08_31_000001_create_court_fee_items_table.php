<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('court_fees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('court_fee_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('court_fee_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('cost', 12, 2);
            $table->timestamps();
            $table->unique(['court_fee_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('court_fee_items');
        Schema::dropIfExists('court_fees');
    }
};
