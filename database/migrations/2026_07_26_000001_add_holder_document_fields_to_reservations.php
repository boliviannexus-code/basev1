<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_groups', function (Blueprint $table): void {
            $table->string('guest_document_type')->nullable()->after('guest_document');
            $table->foreignId('guest_birth_country_id')->nullable()->after('guest_document_type')->constrained('countries')->nullOnDelete();
            $table->date('guest_birth_date')->nullable()->after('guest_birth_country_id');
        });

        Schema::table('reservations', function (Blueprint $table): void {
            $table->string('guest_document_type')->nullable()->after('guest_document');
            $table->foreignId('guest_birth_country_id')->nullable()->after('guest_document_type')->constrained('countries')->nullOnDelete();
            $table->date('guest_birth_date')->nullable()->after('guest_birth_country_id');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('guest_birth_country_id');
            $table->dropColumn(['guest_document_type', 'guest_birth_date']);
        });

        Schema::table('reservation_groups', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('guest_birth_country_id');
            $table->dropColumn(['guest_document_type', 'guest_birth_date']);
        });
    }
};
