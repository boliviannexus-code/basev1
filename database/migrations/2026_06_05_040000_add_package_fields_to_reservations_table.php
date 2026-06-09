<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->foreignId('package_id')
                ->nullable()
                ->after('occupancy_block_id')
                ->constrained('accommodation_packages')
                ->nullOnDelete();
            $table->string('booking_type')->default('normal')->after('package_id');
            $table->json('package_snapshot')->nullable()->after('booking_type');
            $table->decimal('package_price', 10, 2)->nullable()->after('package_snapshot');
            $table->unsignedInteger('included_people')->nullable()->after('package_price');
            $table->unsignedInteger('extra_people')->nullable()->after('included_people');
            $table->decimal('extra_people_total', 10, 2)->nullable()->after('extra_people');
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('advance_amount');

            $table->index(['company_id', 'booking_type']);
            $table->index(['company_id', 'package_id']);
        });

        DB::statement("ALTER TABLE reservations ADD CONSTRAINT reservations_booking_type_check CHECK (booking_type IN ('normal', 'package'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reservations DROP CONSTRAINT IF EXISTS reservations_booking_type_check');

        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'package_id']);
            $table->dropIndex(['company_id', 'booking_type']);
            $table->dropConstrainedForeignId('package_id');
            $table->dropColumn([
                'booking_type',
                'package_snapshot',
                'package_price',
                'included_people',
                'extra_people',
                'extra_people_total',
                'deposit_amount',
            ]);
        });
    }
};
