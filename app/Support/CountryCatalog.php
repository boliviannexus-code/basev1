<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ResourceBundle;

class CountryCatalog
{
    private const EXCLUDED_REGION_CODES = [
        'EU',
        'EZ',
        'QO',
        'UN',
        'XA',
        'XB',
        'ZZ',
    ];

    public static function defaults(): Collection
    {
        $countries = self::countriesFromIntl();

        if ($countries->isEmpty()) {
            $countries = self::fallbackCountries();
        }

        return $countries
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(function (array $country, int $index): array {
                $isBolivia = $country['iso_code'] === 'BO';

                return [
                    'iso_code' => $country['iso_code'],
                    'name' => $country['name'],
                    'is_active' => true,
                    'is_featured' => $isBolivia,
                    'sort_order' => $isBolivia ? 1 : $index + 10,
                ];
            });
    }

    public static function seedForCompany(int $companyId): void
    {
        $now = now();

        self::defaults()
            ->chunk(100)
            ->each(function (Collection $countries) use ($companyId, $now): void {
                $rows = $countries
                    ->map(fn (array $country): array => [
                        'company_id' => $companyId,
                        'iso_code' => $country['iso_code'],
                        'name' => $country['name'],
                        'is_active' => $country['is_active'],
                        'is_featured' => $country['is_featured'],
                        'sort_order' => $country['sort_order'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                DB::table('countries')->upsert(
                    $rows,
                    ['company_id', 'iso_code'],
                    ['name', 'updated_at'],
                );
            });
    }

    private static function countriesFromIntl(): Collection
    {
        if (! class_exists(ResourceBundle::class)) {
            return collect();
        }

        $bundle = new ResourceBundle('es', 'ICUDATA-region');
        $countries = $bundle->get('Countries');

        if (! $countries) {
            return collect();
        }

        $items = [];

        foreach ($countries as $code => $name) {
            if (strlen((string) $code) !== 2 || ! ctype_alpha((string) $code)) {
                continue;
            }

            $code = strtoupper((string) $code);

            if (in_array($code, self::EXCLUDED_REGION_CODES, true)) {
                continue;
            }

            $items[] = [
                'iso_code' => $code,
                'name' => (string) $name,
            ];
        }

        return collect($items);
    }

    private static function fallbackCountries(): Collection
    {
        return collect([
            ['iso_code' => 'AR', 'name' => 'Argentina'],
            ['iso_code' => 'BO', 'name' => 'Bolivia'],
            ['iso_code' => 'BR', 'name' => 'Brasil'],
            ['iso_code' => 'CL', 'name' => 'Chile'],
            ['iso_code' => 'CO', 'name' => 'Colombia'],
            ['iso_code' => 'EC', 'name' => 'Ecuador'],
            ['iso_code' => 'ES', 'name' => 'Espana'],
            ['iso_code' => 'MX', 'name' => 'Mexico'],
            ['iso_code' => 'PE', 'name' => 'Peru'],
            ['iso_code' => 'PY', 'name' => 'Paraguay'],
            ['iso_code' => 'US', 'name' => 'Estados Unidos'],
            ['iso_code' => 'UY', 'name' => 'Uruguay'],
            ['iso_code' => 'VE', 'name' => 'Venezuela'],
        ]);
    }
}
