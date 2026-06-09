<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_charge_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->decimal('default_unit_price', 12, 2)->default(0);
            $table->string('currency', 3)->default('BOB');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_protected')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::table('account_statement_items', function (Blueprint $table): void {
            $table->foreignId('extra_charge_category_id')
                ->nullable()
                ->after('stay_id')
                ->constrained('extra_charge_categories')
                ->nullOnDelete();
        });

        Schema::create('reservation_extra_charges', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('extra_charge_category_id')->nullable()->constrained('extra_charge_categories')->nullOnDelete();
            $table->date('date')->nullable();
            $table->string('detail');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('BOB');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['company_id', 'reservation_id']);
            $table->index(['reservation_id', 'status']);
        });

        DB::statement("ALTER TABLE extra_charge_categories ADD CONSTRAINT extra_charge_categories_currency_check CHECK (currency = 'BOB')");
        DB::statement("ALTER TABLE reservation_extra_charges ADD CONSTRAINT reservation_extra_charges_currency_check CHECK (currency = 'BOB')");
        DB::statement("ALTER TABLE reservation_extra_charges ADD CONSTRAINT reservation_extra_charges_status_check CHECK (status IN ('active', 'cancelled'))");

        $now = now();
        $defaults = [
            ['name' => 'Lavandería', 'sort_order' => 10],
            ['name' => 'Agua', 'sort_order' => 20],
            ['name' => 'Tour', 'sort_order' => 30],
            ['name' => 'Otros', 'sort_order' => 40],
        ];

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach ($defaults as $default) {
                DB::table('extra_charge_categories')->insert([
                    'company_id' => $companyId,
                    'name' => $default['name'],
                    'default_unit_price' => 0,
                    'currency' => 'BOB',
                    'is_active' => true,
                    'is_protected' => true,
                    'sort_order' => $default['sort_order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reservation_extra_charges DROP CONSTRAINT IF EXISTS reservation_extra_charges_status_check');
        DB::statement('ALTER TABLE reservation_extra_charges DROP CONSTRAINT IF EXISTS reservation_extra_charges_currency_check');
        DB::statement('ALTER TABLE extra_charge_categories DROP CONSTRAINT IF EXISTS extra_charge_categories_currency_check');

        Schema::dropIfExists('reservation_extra_charges');

        Schema::table('account_statement_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('extra_charge_category_id');
        });

        Schema::dropIfExists('extra_charge_categories');
    }
};
