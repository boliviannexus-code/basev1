<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->after('company_id')->constrained('categories')->nullOnDelete();
            $table->foreignId('guide_type_id')->nullable()->after('requirements')->constrained('guide_types')->nullOnDelete();
            $table->foreignId('transport_type_id')->nullable()->after('guide_type_id')->constrained('transport_types')->nullOnDelete();
            $table->string('title')->nullable()->after('category_id');
            $table->string('reference_code')->nullable()->after('title');
            $table->text('short_description')->nullable()->after('reference_code');
            $table->text('full_description')->nullable()->after('short_description');
            $table->string('country')->nullable()->after('full_description');
            $table->string('city')->nullable()->after('country');
            $table->string('location_text')->nullable()->after('city');
            $table->json('keywords')->nullable()->after('location_text');
            $table->text('includes')->nullable()->after('keywords');
            $table->text('excludes')->nullable()->after('includes');
            $table->boolean('includes_food')->default(false)->after('transport_type_id');
            $table->text('food_details')->nullable()->after('includes_food');
            $table->boolean('includes_transport')->default(false)->after('food_details');
            $table->text('pets_policy')->nullable()->after('includes_transport');
            $table->text('prohibitions')->nullable()->after('pets_policy');
            $table->text('recommendations')->nullable()->after('prohibitions');
            $table->string('emergency_phone')->nullable()->after('recommendations');
            $table->string('activity_type')->nullable()->after('emergency_phone');
            $table->time('start_time')->nullable()->after('duration');
            $table->time('end_time')->nullable()->after('start_time');
            $table->unsignedInteger('booking_deadline_value')->nullable()->after('meeting_point');
            $table->string('booking_deadline_unit')->nullable()->after('booking_deadline_value');
            $table->unsignedTinyInteger('current_step')->default(1)->after('status');

            $table->unique(['company_id', 'title']);
            $table->unique(['company_id', 'reference_code']);
            $table->index(['company_id', 'current_step']);
        });

        Schema::create('tour_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->boolean('is_main')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['tour_id', 'is_main']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_images');

        Schema::table('tours', function (Blueprint $table): void {
            $table->dropUnique(['company_id', 'title']);
            $table->dropUnique(['company_id', 'reference_code']);
            $table->dropIndex(['company_id', 'current_step']);
            $table->dropConstrainedForeignId('category_id');
            $table->dropConstrainedForeignId('guide_type_id');
            $table->dropConstrainedForeignId('transport_type_id');
            $table->dropColumn([
                'title',
                'reference_code',
                'short_description',
                'full_description',
                'country',
                'city',
                'location_text',
                'keywords',
                'includes',
                'excludes',
                'includes_food',
                'food_details',
                'includes_transport',
                'pets_policy',
                'prohibitions',
                'recommendations',
                'emergency_phone',
                'activity_type',
                'start_time',
                'end_time',
                'booking_deadline_value',
                'booking_deadline_unit',
                'current_step',
            ]);
        });
    }
};
