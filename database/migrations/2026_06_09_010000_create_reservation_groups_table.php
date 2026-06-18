<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_channel_id')->nullable()->constrained('reservation_channels')->nullOnDelete();
            $table->string('code')->unique();
            $table->string('guest_name');
            $table->string('guest_email')->nullable();
            $table->string('guest_phone')->nullable();
            $table->string('guest_document')->nullable();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedInteger('nights');
            $table->unsignedInteger('guests');
            $table->decimal('subtotal_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('advance_amount', 10, 2)->default(0);
            $table->decimal('balance_amount', 10, 2);
            $table->string('currency', 3)->default('BOB');
            $table->string('status')->default('pending_payment');
            $table->string('payment_status')->default('pending');
            $table->string('payment_method')->nullable();
            $table->string('payment_reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'check_in', 'check_out']);
            $table->index(['company_id', 'reservation_channel_id']);
        });

        DB::statement("ALTER TABLE reservation_groups ADD CONSTRAINT reservation_groups_status_check CHECK (status IN ('pending_payment', 'confirmed', 'cancelled'))");
        DB::statement("ALTER TABLE reservation_groups ADD CONSTRAINT reservation_groups_payment_status_check CHECK (payment_status IN ('pending', 'partial', 'validated'))");

        Schema::table('reservations', function (Blueprint $table): void {
            $table->foreignId('reservation_group_id')->nullable()->after('company_id')->constrained('reservation_groups')->nullOnDelete();
            $table->index(['company_id', 'reservation_group_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reservation_group_id');
        });

        DB::statement('ALTER TABLE reservation_groups DROP CONSTRAINT IF EXISTS reservation_groups_payment_status_check');
        DB::statement('ALTER TABLE reservation_groups DROP CONSTRAINT IF EXISTS reservation_groups_status_check');
        Schema::dropIfExists('reservation_groups');
    }
};
