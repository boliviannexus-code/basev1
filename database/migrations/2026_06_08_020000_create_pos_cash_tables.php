<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'branch_id']);
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('measurement_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('abbreviation', 20);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('presentations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('units_per_package')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('measurement_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('barcode')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('minimum_stock', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'barcode']);
            $table->index(['company_id', 'name']);
        });

        Schema::create('product_presentation', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('presentation_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2)->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'presentation_id']);
        });

        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('company_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('document_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'name']);
            $table->index(['company_id', 'document_number']);
        });

        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference');
            $table->unsignedInteger('sequence_number');
            $table->date('purchase_date');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status')->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'status']);
        });

        Schema::create('purchase_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('presentation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('presentation_name');
            $table->decimal('package_quantity', 12, 2);
            $table->unsignedInteger('units_per_package')->default(1);
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });

        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('presentation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('presentation_name')->nullable();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->decimal('quantity', 12, 2);
            $table->decimal('package_quantity', 12, 2)->nullable();
            $table->unsignedInteger('units_per_package')->default(1);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamps();
            $table->index(['warehouse_id', 'product_id']);
        });

        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'name']);
        });

        Schema::create('point_of_sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->string('receipt_prefix')->default('POS');
            $table->unsignedInteger('sequence_number')->default(1);
            $table->unsignedInteger('receipt_next_number')->default(1);
            $table->unsignedTinyInteger('receipt_digits')->default(6);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('point_of_sale_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('point_of_sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['point_of_sale_id', 'user_id']);
        });

        Schema::create('cash_registers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_of_sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('opening_amount', 12, 2)->default(0);
            $table->decimal('closing_amount', 12, 2)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('cash_register_expenses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_of_sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('responsible_name');
            $table->string('detail');
            $table->decimal('amount', 12, 2);
            $table->timestamp('spent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_of_sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable();
            $table->string('receipt_number');
            $table->unsignedInteger('sequence_number');
            $table->timestamp('sale_date')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('cash_received', 12, 2)->nullable();
            $table->decimal('cash_change', 12, 2)->nullable();
            $table->string('status')->default('completed');
            $table->timestamps();
            $table->unique(['cash_register_id', 'receipt_number']);
            $table->index(['cash_register_id', 'status']);
        });

        Schema::create('sale_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('presentation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name')->nullable();
            $table->string('presentation_name')->nullable();
            $table->decimal('package_quantity', 12, 2)->default(0);
            $table->unsignedInteger('units_per_package')->default(1);
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method_name');
            $table->decimal('amount', 12, 2);
            $table->decimal('received_amount', 12, 2)->nullable();
            $table->decimal('change_amount', 12, 2)->nullable();
            $table->string('reference')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_register_user_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_prefix');
            $table->unsignedInteger('receipt_next_number')->default(1);
            $table->unsignedTinyInteger('receipt_digits')->default(6);
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });

        Schema::create('cash_register_lodging_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('point_of_sale_id')->nullable()->constrained()->nullOnDelete();
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
            $table->index(['cash_register_id', 'status']);
            $table->index(['company_id', 'stay_id']);
        });

        DB::statement("ALTER TABLE cash_registers ADD CONSTRAINT cash_registers_status_check CHECK (status IN ('open', 'closed'))");
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_status_check CHECK (status IN ('completed', 'voided'))");
        DB::statement("ALTER TABLE cash_register_lodging_payments ADD CONSTRAINT cash_register_lodging_payments_currency_check CHECK (currency_original IN ('BOB', 'USD'))");
        DB::statement("ALTER TABLE cash_register_lodging_payments ADD CONSTRAINT cash_register_lodging_payments_status_check CHECK (status IN ('active', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_lodging_payments');
        Schema::dropIfExists('cash_register_user_sequences');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_details');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('cash_register_expenses');
        Schema::dropIfExists('cash_registers');
        Schema::dropIfExists('point_of_sale_user');
        Schema::dropIfExists('point_of_sales');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('purchase_details');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('product_presentation');
        Schema::dropIfExists('products');
        Schema::dropIfExists('presentations');
        Schema::dropIfExists('measurement_units');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('branches');
    }
};
