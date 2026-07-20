<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'public_page_title')) {
                $table->string('public_page_title')->nullable()->after('report_footer');
            }

            if (! Schema::hasColumn('companies', 'public_page_summary')) {
                $table->text('public_page_summary')->nullable()->after('public_page_title');
            }

            if (! Schema::hasColumn('companies', 'public_page_body')) {
                $table->text('public_page_body')->nullable()->after('public_page_summary');
            }

            if (! Schema::hasColumn('companies', 'public_contact_text')) {
                $table->text('public_contact_text')->nullable()->after('public_page_body');
            }

            if (! Schema::hasColumn('companies', 'public_whatsapp')) {
                $table->string('public_whatsapp')->nullable()->after('public_contact_text');
            }

            if (! Schema::hasColumn('companies', 'public_facebook_url')) {
                $table->string('public_facebook_url')->nullable()->after('public_whatsapp');
            }

            if (! Schema::hasColumn('companies', 'public_instagram_url')) {
                $table->string('public_instagram_url')->nullable()->after('public_facebook_url');
            }

            if (! Schema::hasColumn('companies', 'public_tiktok_url')) {
                $table->string('public_tiktok_url')->nullable()->after('public_instagram_url');
            }

            if (! Schema::hasColumn('companies', 'public_youtube_url')) {
                $table->string('public_youtube_url')->nullable()->after('public_tiktok_url');
            }

            if (! Schema::hasColumn('companies', 'public_banner_path')) {
                $table->string('public_banner_path')->nullable()->after('public_youtube_url');
            }

            if (! Schema::hasColumn('companies', 'public_image_one_path')) {
                $table->string('public_image_one_path')->nullable()->after('public_banner_path');
            }

            if (! Schema::hasColumn('companies', 'public_image_two_path')) {
                $table->string('public_image_two_path')->nullable()->after('public_image_one_path');
            }

            if (! Schema::hasColumn('companies', 'public_page_is_enabled')) {
                $table->boolean('public_page_is_enabled')->default(false)->after('public_image_two_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $columns = collect([
                'public_page_title',
                'public_page_summary',
                'public_page_body',
                'public_contact_text',
                'public_whatsapp',
                'public_facebook_url',
                'public_instagram_url',
                'public_tiktok_url',
                'public_youtube_url',
                'public_banner_path',
                'public_image_one_path',
                'public_image_two_path',
                'public_page_is_enabled',
            ])->filter(fn (string $column): bool => Schema::hasColumn('companies', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
