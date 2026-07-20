<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
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
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $columns = collect([
                'public_whatsapp',
                'public_facebook_url',
                'public_instagram_url',
                'public_tiktok_url',
                'public_youtube_url',
            ])->filter(fn (string $column): bool => Schema::hasColumn('companies', $column))->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
