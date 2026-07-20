<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_cities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('geoname_id')->unique();
            $table->string('name');
            $table->string('ascii_name')->nullable();
            $table->text('alternate_names')->nullable();
            $table->string('country_code', 2)->index();
            $table->string('country_name')->nullable()->index();
            $table->string('admin1_code', 20)->nullable();
            $table->string('admin1_name')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('population')->default(0)->index();
            $table->string('timezone')->nullable();
            $table->string('feature_code', 20)->nullable();
            $table->text('search_text');
            $table->timestamps();

            $table->index(['country_code', 'ascii_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_cities');
    }
};
