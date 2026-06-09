<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('space_rooms', function (Blueprint $table): void {
            $table->string('sale_mode')->default('full_room')->after('max_capacity');
            $table->index(['company_id', 'sale_mode']);
        });

        DB::statement("ALTER TABLE space_rooms ADD CONSTRAINT space_rooms_sale_mode_check CHECK (sale_mode IN ('full_room', 'bed_unit', 'flexible'))");

        Schema::create('room_bed_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('space_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_bed_id')->nullable()->constrained('room_beds')->nullOnDelete();
            $table->foreignId('bed_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('code')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'space_room_id']);
            $table->index(['room_bed_id']);
            $table->index(['status']);
        });

        DB::statement("ALTER TABLE room_bed_units ADD CONSTRAINT room_bed_units_status_check CHECK (status IN ('active', 'inactive'))");

        Schema::table('occupancy_blocks', function (Blueprint $table): void {
            $table->foreignId('room_bed_unit_id')->nullable()->after('space_room_id')->constrained('room_bed_units')->nullOnDelete();
            $table->index(['company_id', 'room_bed_unit_id']);
        });

        Schema::table('availability_statuses', function (Blueprint $table): void {
            $table->foreignId('room_bed_unit_id')->nullable()->after('space_room_id')->constrained('room_bed_units')->nullOnDelete();
            $table->index(['company_id', 'room_bed_unit_id']);
        });

        DB::statement('DROP INDEX IF EXISTS availability_statuses_private_unique');
        DB::statement('DROP INDEX IF EXISTS availability_statuses_room_unique');
        DB::statement('CREATE UNIQUE INDEX availability_statuses_private_unique ON availability_statuses (company_id, space_id, date) WHERE space_room_id IS NULL AND room_bed_unit_id IS NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX availability_statuses_room_unique ON availability_statuses (company_id, space_room_id, date) WHERE space_room_id IS NOT NULL AND room_bed_unit_id IS NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX availability_statuses_bed_unit_unique ON availability_statuses (company_id, room_bed_unit_id, date) WHERE room_bed_unit_id IS NOT NULL AND deleted_at IS NULL');

        Schema::create('reservation_bed_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_bed_unit_id')->constrained('room_bed_units')->cascadeOnDelete();
            $table->foreignId('occupancy_block_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_name')->nullable();
            $table->decimal('price_per_night', 10, 2);
            $table->decimal('subtotal_amount', 10, 2);
            $table->timestamps();

            $table->unique(['reservation_id', 'room_bed_unit_id']);
            $table->index(['room_bed_unit_id', 'reservation_id']);
        });

        $this->seedExistingBedUnits();
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_bed_units');

        DB::statement('DROP INDEX IF EXISTS availability_statuses_bed_unit_unique');
        DB::statement('DROP INDEX IF EXISTS availability_statuses_private_unique');
        DB::statement('DROP INDEX IF EXISTS availability_statuses_room_unique');
        DB::statement('CREATE UNIQUE INDEX availability_statuses_private_unique ON availability_statuses (company_id, space_id, date) WHERE space_room_id IS NULL AND deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX availability_statuses_room_unique ON availability_statuses (company_id, space_room_id, date) WHERE space_room_id IS NOT NULL AND deleted_at IS NULL');

        Schema::table('availability_statuses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('room_bed_unit_id');
        });

        Schema::table('occupancy_blocks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('room_bed_unit_id');
        });

        Schema::dropIfExists('room_bed_units');

        DB::statement('ALTER TABLE space_rooms DROP CONSTRAINT IF EXISTS space_rooms_sale_mode_check');

        Schema::table('space_rooms', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'sale_mode']);
            $table->dropColumn('sale_mode');
        });
    }

    private function seedExistingBedUnits(): void
    {
        $now = now();
        $sortOrders = [];

        DB::table('room_beds')
            ->orderBy('space_room_id')
            ->orderBy('id')
            ->chunkById(200, function ($beds) use ($now, &$sortOrders): void {
                foreach ($beds as $bed) {
                    $roomId = (int) $bed->space_room_id;
                    $sortOrders[$roomId] ??= 0;

                    for ($index = 1; $index <= (int) $bed->quantity; $index++) {
                        $sortOrders[$roomId]++;
                        $label = 'Cama '.$this->letterLabel($sortOrders[$roomId]);

                        DB::table('room_bed_units')->insert([
                            'company_id' => $bed->company_id,
                            'space_room_id' => $roomId,
                            'room_bed_id' => $bed->id,
                            'bed_type_id' => $bed->bed_type_id,
                            'label' => $label,
                            'code' => 'BED-'.$roomId.'-'.$sortOrders[$roomId],
                            'sort_order' => $sortOrders[$roomId],
                            'status' => 'active',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            });
    }

    private function letterLabel(int $number): string
    {
        $label = '';

        while ($number > 0) {
            $number--;
            $label = chr(65 + ($number % 26)).$label;
            $number = intdiv($number, 26);
        }

        return $label;
    }
};
