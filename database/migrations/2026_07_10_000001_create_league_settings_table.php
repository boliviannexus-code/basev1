<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->decimal('transfer_fee', 10, 2)->default(0);
            $table->decimal('yellow_card_fee', 10, 2)->default(0);
            $table->decimal('red_card_fee', 10, 2)->default(0);
            $table->decimal('court_fee', 10, 2)->default(0);
            $table->decimal('medical_fee', 10, 2)->default(0);
            $table->decimal('scorer_fee', 10, 2)->default(0);
            $table->decimal('ballboy_fee', 10, 2)->default(0);
            $table->decimal('referee_fee', 10, 2)->default(0);
            $table->decimal('medicine_fee', 10, 2)->default(0);
            $table->decimal('insurance_fee', 10, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('company_id');
        });

        if (Schema::hasTable('player_transfer_settings')) {
            DB::table('player_transfer_settings')
                ->orderBy('id')
                ->get()
                ->each(function (object $setting): void {
                    DB::table('league_settings')->updateOrInsert(
                        ['company_id' => $setting->company_id],
                        [
                            'transfer_fee' => $setting->fee_amount ?? 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('league_settings');
    }
};
