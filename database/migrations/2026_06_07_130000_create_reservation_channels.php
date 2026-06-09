<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type')->default('direct');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->decimal('commission_percent', 5, 2)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_protected')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'is_active']);
        });

        DB::statement("ALTER TABLE reservation_channels ADD CONSTRAINT reservation_channels_type_check CHECK (type IN ('direct', 'ota', 'agency', 'corporate', 'other'))");

        Schema::table('reservations', function (Blueprint $table): void {
            $table->foreignId('reservation_channel_id')->nullable()->after('package_id')->constrained('reservation_channels')->nullOnDelete();
            $table->index(['company_id', 'reservation_channel_id']);
        });

        $this->seedDefaultChannels();
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reservation_channel_id');
        });

        DB::statement('ALTER TABLE reservation_channels DROP CONSTRAINT IF EXISTS reservation_channels_type_check');
        Schema::dropIfExists('reservation_channels');
    }

    private function seedDefaultChannels(): void
    {
        $now = now();
        $defaults = [
            ['name' => 'Walk-in', 'type' => 'direct', 'sort_order' => 1],
            ['name' => 'Booking', 'type' => 'ota', 'sort_order' => 2],
            ['name' => 'Hostelworld', 'type' => 'ota', 'sort_order' => 3],
            ['name' => 'Agencia', 'type' => 'agency', 'sort_order' => 4],
        ];

        DB::table('companies')
            ->orderBy('id')
            ->chunkById(200, function ($companies) use ($defaults, $now): void {
                foreach ($companies as $company) {
                    foreach ($defaults as $channel) {
                        DB::table('reservation_channels')->insert([
                            'company_id' => $company->id,
                            'name' => $channel['name'],
                            'slug' => Str::slug($channel['name']),
                            'type' => $channel['type'],
                            'is_active' => true,
                            'is_protected' => $channel['name'] === 'Walk-in',
                            'sort_order' => $channel['sort_order'],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                }
            });
    }
};
