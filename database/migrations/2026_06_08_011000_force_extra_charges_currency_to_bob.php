<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('extra_charge_categories')->update(['currency' => 'BOB']);
        DB::table('reservation_extra_charges')->update(['currency' => 'BOB']);
        DB::table('account_statement_items')
            ->where('type', 'extra')
            ->whereNotNull('extra_charge_category_id')
            ->update(['currency' => 'BOB']);

        DB::statement('ALTER TABLE extra_charge_categories DROP CONSTRAINT IF EXISTS extra_charge_categories_currency_check');
        DB::statement('ALTER TABLE reservation_extra_charges DROP CONSTRAINT IF EXISTS reservation_extra_charges_currency_check');
        DB::statement("ALTER TABLE extra_charge_categories ADD CONSTRAINT extra_charge_categories_currency_check CHECK (currency = 'BOB')");
        DB::statement("ALTER TABLE reservation_extra_charges ADD CONSTRAINT reservation_extra_charges_currency_check CHECK (currency = 'BOB')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE extra_charge_categories DROP CONSTRAINT IF EXISTS extra_charge_categories_currency_check');
        DB::statement('ALTER TABLE reservation_extra_charges DROP CONSTRAINT IF EXISTS reservation_extra_charges_currency_check');
        DB::statement("ALTER TABLE extra_charge_categories ADD CONSTRAINT extra_charge_categories_currency_check CHECK (currency IN ('BOB', 'USD'))");
        DB::statement("ALTER TABLE reservation_extra_charges ADD CONSTRAINT reservation_extra_charges_currency_check CHECK (currency IN ('BOB', 'USD'))");
    }
};
