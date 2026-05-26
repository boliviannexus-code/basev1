<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->string('review_status')->default('draft')->after('status');
            $table->json('rejection_points')->nullable()->after('review_status');
            $table->foreignId('reviewed_by')->nullable()->after('rejection_points')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');

            $table->index(['company_id', 'review_status']);
        });

        Schema::create('tour_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->unsignedInteger('min_people');
            $table->unsignedInteger('max_people')->nullable();
            $table->decimal('price_usd', 10, 2);
            $table->timestamps();

            $table->index(['tour_id', 'min_people']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_prices');

        Schema::table('tours', function (Blueprint $table): void {
            $table->dropIndex(['company_id', 'review_status']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['review_status', 'rejection_points', 'reviewed_at']);
        });
    }
};
