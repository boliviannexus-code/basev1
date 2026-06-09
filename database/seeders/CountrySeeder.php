<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Support\CountryCatalog;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        Company::query()
            ->orderBy('id')
            ->chunkById(100, function ($companies): void {
                foreach ($companies as $company) {
                    CountryCatalog::seedForCompany((int) $company->id);
                }
            });
    }
}
