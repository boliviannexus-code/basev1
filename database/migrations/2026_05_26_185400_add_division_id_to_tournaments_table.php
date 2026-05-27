<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Deprecated: la relacion torneo-division se maneja en migraciones posteriores.
    }

    public function down(): void
    {
        // Deprecated: no se revierte nada porque esta migracion ya no modifica torneos.
    }
};
