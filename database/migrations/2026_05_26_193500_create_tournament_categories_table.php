<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Deprecated: las categorias son universales por division, no por torneo.
    }

    public function down(): void
    {
        // Deprecated: no se revierte nada porque esta migracion ya no crea tablas.
    }
};
