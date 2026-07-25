<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->string('interface_primary_color', 7)->nullable()->after('public_page_is_enabled');
            $table->string('interface_secondary_color', 7)->nullable()->after('interface_primary_color');
            $table->string('interface_accent_color', 7)->nullable()->after('interface_secondary_color');
            $table->string('interface_sidebar_color', 7)->nullable()->after('interface_accent_color');
            $table->string('interface_login_background_color', 7)->nullable()->after('interface_sidebar_color');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn([
                'interface_primary_color',
                'interface_secondary_color',
                'interface_accent_color',
                'interface_sidebar_color',
                'interface_login_background_color',
            ]);
        });
    }
};
