<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type')->default('ci');
            $table->string('document_number')->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->foreignId('birth_country_id')->nullable()->constrained('countries')->nullOnDelete();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'document_type', 'document_number']);
            $table->index(['company_id', 'last_name', 'first_name']);
            $table->index(['company_id', 'birth_country_id']);
        });

        Schema::create('check_in_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->foreignId('main_guest_id')->constrained('guests')->restrictOnDelete();
            $table->foreignId('reservation_channel_id')->nullable()->constrained('reservation_channels')->nullOnDelete();
            $table->unsignedInteger('total_people');
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->string('status')->default('checked_in');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'check_in_date', 'check_out_date']);
        });

        Schema::create('stays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('check_in_group_id')->constrained('check_in_groups')->cascadeOnDelete();
            $table->foreignId('holder_guest_id')->constrained('guests')->restrictOnDelete();
            $table->foreignId('space_id')->constrained()->restrictOnDelete();
            $table->foreignId('space_room_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('room_bed_unit_id')->nullable()->constrained('room_bed_units')->restrictOnDelete();
            $table->unsignedInteger('people_count');
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->unsignedInteger('nights');
            $table->decimal('price_per_night_bob', 12, 2)->nullable();
            $table->decimal('price_per_night_usd', 12, 2)->nullable();
            $table->decimal('exchange_rate', 12, 4)->nullable();
            $table->string('currency', 3)->default('BOB');
            $table->boolean('breakfast_included')->default(false);
            $table->string('status')->default('occupied');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'check_in_group_id']);
            $table->index(['company_id', 'space_id', 'space_room_id']);
            $table->index(['company_id', 'room_bed_unit_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'check_in_date', 'check_out_date']);
        });

        Schema::create('stay_guests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stay_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_holder')->default(false);
            $table->timestamps();

            $table->unique(['stay_id', 'guest_id']);
            $table->index(['company_id', 'stay_id']);
            $table->index(['company_id', 'guest_id']);
        });

        Schema::create('account_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stay_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3)->default('BOB');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('extra_charges_total', 12, 2)->default(0);
            $table->decimal('payments_total', 12, 2)->default(0);
            $table->decimal('balance', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique('stay_id');
            $table->index(['company_id', 'status']);
        });

        Schema::create('account_statement_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stay_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date')->nullable();
            $table->string('type');
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->string('currency', 3)->default('BOB');
            $table->string('source')->default('check_in');
            $table->timestamps();

            $table->index(['company_id', 'account_statement_id']);
            $table->index(['company_id', 'stay_id']);
            $table->index(['company_id', 'date']);
            $table->index(['type']);
            $table->index(['source']);
        });

        DB::statement("ALTER TABLE guests ADD CONSTRAINT guests_document_type_check CHECK (document_type IN ('passport', 'dni', 'ci', 'other'))");
        DB::statement("ALTER TABLE check_in_groups ADD CONSTRAINT check_in_groups_status_check CHECK (status IN ('checked_in', 'checked_out', 'cancelled'))");
        DB::statement('ALTER TABLE check_in_groups ADD CONSTRAINT check_in_groups_people_check CHECK (total_people > 0)');
        DB::statement('ALTER TABLE check_in_groups ADD CONSTRAINT check_in_groups_date_check CHECK (check_out_date > check_in_date)');
        DB::statement("ALTER TABLE stays ADD CONSTRAINT stays_currency_check CHECK (currency IN ('BOB', 'USD'))");
        DB::statement("ALTER TABLE stays ADD CONSTRAINT stays_status_check CHECK (status IN ('occupied', 'checked_out', 'cancelled'))");
        DB::statement('ALTER TABLE stays ADD CONSTRAINT stays_people_check CHECK (people_count > 0)');
        DB::statement('ALTER TABLE stays ADD CONSTRAINT stays_nights_check CHECK (nights > 0)');
        DB::statement('ALTER TABLE stays ADD CONSTRAINT stays_date_check CHECK (check_out_date > check_in_date)');
        DB::statement('ALTER TABLE stays ADD CONSTRAINT stays_room_bed_unit_requires_room_check CHECK (room_bed_unit_id IS NULL OR space_room_id IS NOT NULL)');
        DB::statement("ALTER TABLE account_statements ADD CONSTRAINT account_statements_currency_check CHECK (currency IN ('BOB', 'USD'))");
        DB::statement("ALTER TABLE account_statements ADD CONSTRAINT account_statements_status_check CHECK (status IN ('pending', 'partial', 'paid'))");
        DB::statement("ALTER TABLE account_statement_items ADD CONSTRAINT account_statement_items_type_check CHECK (type IN ('lodging_night', 'breakfast', 'extra', 'discount', 'payment', 'adjustment'))");
        DB::statement("ALTER TABLE account_statement_items ADD CONSTRAINT account_statement_items_currency_check CHECK (currency IN ('BOB', 'USD'))");
        DB::statement("ALTER TABLE account_statement_items ADD CONSTRAINT account_statement_items_source_check CHECK (source IN ('check_in', 'manual', 'system'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('account_statement_items');
        Schema::dropIfExists('account_statements');
        Schema::dropIfExists('stay_guests');
        Schema::dropIfExists('stays');
        Schema::dropIfExists('check_in_groups');
        Schema::dropIfExists('guests');
    }
};
