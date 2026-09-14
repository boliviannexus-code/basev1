<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('matchday_court_fee_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('matchday_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('revision')->default(0);
            $table->timestamp('consolidated_at')->nullable();
            $table->foreignId('consolidated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('source_changed_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('matchday_court_fee_statements'); }
};
