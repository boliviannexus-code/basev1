<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_cash_incomes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extra_charge_category_id')->nullable()->constrained('extra_charge_categories')->nullOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('receipt_number');
            $table->string('responsible_name')->nullable();
            $table->string('detail');
            $table->string('reference')->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'receipt_number']);
            $table->index(['space_cash_register_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_cash_incomes');
    }
};
