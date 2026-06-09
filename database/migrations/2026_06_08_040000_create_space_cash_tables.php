<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_cash_registers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('opening_amount', 12, 2)->default(0);
            $table->decimal('closing_amount', 12, 2)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
            $table->index(['company_id', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('space_cash_user_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_prefix');
            $table->unsignedInteger('receipt_next_number')->default(1);
            $table->unsignedTinyInteger('receipt_digits')->default(6);
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('space_cash_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('responsible_name');
            $table->string('detail');
            $table->decimal('amount', 12, 2);
            $table->timestamp('spent_at')->nullable();
            $table->timestamps();
            $table->index(['space_cash_register_id']);
        });

        Schema::create('space_cash_lodging_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_statement_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stay_id')->constrained()->cascadeOnDelete();
            $table->foreignId('check_in_group_id')->constrained('check_in_groups')->cascadeOnDelete();
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
            $table->index(['company_id', 'stay_id']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE space_cash_registers ADD CONSTRAINT space_cash_registers_status_check CHECK (status IN ('open', 'closed'))");
            DB::statement("ALTER TABLE space_cash_lodging_payments ADD CONSTRAINT space_cash_lodging_payments_currency_check CHECK (currency_original IN ('BOB', 'USD'))");
            DB::statement("ALTER TABLE space_cash_lodging_payments ADD CONSTRAINT space_cash_lodging_payments_status_check CHECK (status IN ('active', 'cancelled'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('space_cash_lodging_payments');
        Schema::dropIfExists('space_cash_expenses');
        Schema::dropIfExists('space_cash_user_sequences');
        Schema::dropIfExists('space_cash_registers');
    }
};
