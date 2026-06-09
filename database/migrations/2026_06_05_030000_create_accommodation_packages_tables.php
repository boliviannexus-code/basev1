<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('package_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'is_active', 'sort_order']);
            $table->index(['company_id', 'type']);
        });

        Schema::create('accommodation_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('short_description');
            $table->text('commercial_description')->nullable();
            $table->text('conditions')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('BOB');
            $table->unsignedInteger('included_people');
            $table->unsignedInteger('max_people')->nullable();
            $table->decimal('extra_person_price', 10, 2)->nullable();
            $table->boolean('requires_full_private_space')->default(true);
            $table->unsignedInteger('nights_included')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('main_image')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'slug']);
            $table->index(['company_id', 'is_active', 'is_featured']);
            $table->index(['company_id', 'sort_order']);
        });

        Schema::create('accommodation_package_space', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('accommodation_packages')->cascadeOnDelete();
            $table->foreignId('space_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['package_id', 'space_id']);
            $table->index(['space_id', 'package_id']);
        });

        Schema::create('accommodation_package_service', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('accommodation_packages')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('package_services')->cascadeOnDelete();
            $table->string('inclusion_type')->default('included');
            $table->string('custom_name')->nullable();
            $table->text('custom_description')->nullable();
            $table->decimal('additional_price', 10, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['package_id', 'service_id']);
            $table->index(['service_id', 'package_id']);
            $table->index(['package_id', 'inclusion_type', 'sort_order']);
        });

        DB::statement("ALTER TABLE accommodation_package_service ADD CONSTRAINT accommodation_package_service_inclusion_type_check CHECK (inclusion_type IN ('included', 'optional_paid', 'not_included'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('accommodation_package_service');
        Schema::dropIfExists('accommodation_package_space');
        Schema::dropIfExists('accommodation_packages');
        Schema::dropIfExists('package_services');
    }
};
