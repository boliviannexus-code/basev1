<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('online_check_in_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name');
            $table->string('document_number', 60);
            $table->foreignId('country_id')->constrained()->restrictOnDelete();
            $table->date('birth_date');
            $table->date('confirmed_checkout');
            $table->decimal('confirmed_price', 12, 2);
            $table->string('price_currency', 3);
            $table->decimal('exchange_rate', 12, 4)->nullable();
            $table->decimal('price_usd', 12, 2)->nullable();
            $table->decimal('price_local', 12, 2)->nullable();
            $table->string('local_currency', 3);
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['company_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('online_check_in_submissions');
    }
};
