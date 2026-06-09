<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'public_slug')) {
                $table->string('public_slug')->nullable()->unique();
            }

            if (! Schema::hasColumn('companies', 'public_name')) {
                $table->string('public_name')->nullable();
            }

            if (! Schema::hasColumn('companies', 'public_description')) {
                $table->text('public_description')->nullable();
            }

            if (! Schema::hasColumn('companies', 'logo')) {
                $table->string('logo')->nullable();
            }

            if (! Schema::hasColumn('companies', 'cover_image')) {
                $table->string('cover_image')->nullable();
            }

            if (! Schema::hasColumn('companies', 'whatsapp')) {
                $table->string('whatsapp')->nullable();
            }

            if (! Schema::hasColumn('companies', 'website')) {
                $table->string('website')->nullable();
            }

            if (! Schema::hasColumn('companies', 'location_reference')) {
                $table->string('location_reference')->nullable();
            }

            if (! Schema::hasColumn('companies', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('companies', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }

            if (! Schema::hasColumn('companies', 'facebook_url')) {
                $table->string('facebook_url')->nullable();
            }

            if (! Schema::hasColumn('companies', 'instagram_url')) {
                $table->string('instagram_url')->nullable();
            }

            if (! Schema::hasColumn('companies', 'tiktok_url')) {
                $table->string('tiktok_url')->nullable();
            }

            if (! Schema::hasColumn('companies', 'is_public_enabled')) {
                $table->boolean('is_public_enabled')->default(false);
            }

            if (! Schema::hasColumn('companies', 'is_online_enabled_by_admin')) {
                $table->boolean('is_online_enabled_by_admin')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $columns = [
                'public_slug',
                'public_name',
                'public_description',
                'logo',
                'cover_image',
                'whatsapp',
                'website',
                'location_reference',
                'latitude',
                'longitude',
                'facebook_url',
                'instagram_url',
                'tiktok_url',
                'is_public_enabled',
                'is_online_enabled_by_admin',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
