<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_room_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('occupancy_block_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code')->unique();
            $table->string('guest_name');
            $table->string('guest_email');
            $table->string('guest_phone')->nullable();
            $table->string('guest_country')->nullable();
            $table->string('guest_document')->nullable();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedInteger('nights');
            $table->unsignedInteger('guests');
            $table->decimal('price_per_person', 10, 2);
            $table->decimal('subtotal_amount', 10, 2);
            $table->decimal('total_amount', 10, 2);
            $table->decimal('advance_amount', 10, 2);
            $table->decimal('balance_amount', 10, 2);
            $table->string('currency', 3)->default('BOB');
            $table->string('status')->default('pending_payment');
            $table->string('payment_status')->default('pending');
            $table->string('payment_method')->default('qr');
            $table->string('payment_reference')->nullable();
            $table->string('payment_proof_path')->nullable();
            $table->timestamp('hold_expires_at')->nullable();
            $table->text('guest_notes')->nullable();
            $table->timestamp('payment_validated_at')->nullable();
            $table->foreignId('payment_validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'space_id', 'space_room_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['check_in', 'check_out']);
            $table->index(['hold_expires_at']);
        });

        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_status_check CHECK (status IN ('pending_data', 'pending_payment', 'payment_under_review', 'confirmed', 'rejected', 'cancelled', 'expired'))");
        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_payment_status_check CHECK (payment_status IN ('pending', 'submitted', 'validated', 'rejected'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
