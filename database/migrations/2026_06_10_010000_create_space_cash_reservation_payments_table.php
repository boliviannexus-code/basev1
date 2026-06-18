<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_cash_reservation_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_statement_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_group_id')->constrained('reservation_groups')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->restrictOnDelete();
            $table->string('receipt_number');
            $table->string('reference')->nullable();
            $table->decimal('amount_original', 12, 2);
            $table->string('currency_original', 3);
            $table->decimal('exchange_rate', 12, 4)->default(1);
            $table->decimal('amount_bob', 12, 2);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['company_id', 'receipt_number']);
            $table->index(['space_cash_register_id', 'status']);
            $table->index(['company_id', 'reservation_group_id']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE space_cash_reservation_payments ADD CONSTRAINT space_cash_reservation_payments_currency_check CHECK (currency_original IN ('BOB', 'USD'))");
            DB::statement("ALTER TABLE space_cash_reservation_payments ADD CONSTRAINT space_cash_reservation_payments_status_check CHECK (status IN ('active', 'cancelled'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('space_cash_reservation_payments');
    }
};
