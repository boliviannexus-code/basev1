<?php

namespace App\Support;

use App\Models\LocationCity;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LocalLocations
{
    public function search(string $query = '', ?string $type = null, ?string $country = null, int $limit = 20): Collection
    {
        $query = $this->normalize($query);
        $country = $this->normalize($country ?? '');
        $type = $type ?: 'all';
        $limit = max(1, min($limit, 50));

        $databaseResults = $this->searchDatabase($query, $type, $country, $limit);

        if ($databaseResults !== null) {
            return $databaseResults;
        }

        $results = collect();

        foreach ($this->countries() as $countryRow) {
            $countryName = (string) $countryRow['name'];
            $countryCode = (string) $countryRow['code'];
            $normalizedCountryName = $this->normalize($countryName);
            $normalizedCountryCode = $this->normalize($countryCode);

            if ($type !== 'city' && $this->matches($query, $normalizedCountryName, $normalizedCountryCode)) {
                $results->push([
                    'type' => 'country',
                    'value' => $countryName,
                    'label' => $countryName,
                    'country' => $countryName,
                    'country_code' => $countryCode,
                ]);
            }

            if ($type === 'country') {
                continue;
            }

            if ($country !== '' && ! in_array($country, [$normalizedCountryName, $normalizedCountryCode], true)) {
                continue;
            }

            foreach (Arr::get($countryRow, 'cities', []) as $city) {
                $cityName = (string) $city['name'];
                $region = (string) ($city['region'] ?? '');
                $haystack = $this->normalize($cityName.' '.$region.' '.$countryName.' '.$countryCode);

                if ($query !== '' && ! str_contains($haystack, $query)) {
                    continue;
                }

                $results->push([
                    'type' => 'city',
                    'value' => $countryCode.':'.$cityName,
                    'label' => trim($cityName.', '.$region.' - '.$countryName, ' ,-'),
                    'city' => $cityName,
                    'region' => $region,
                    'country' => $countryName,
                    'country_code' => $countryCode,
                    'latitude' => $city['latitude'] ?? null,
                    'longitude' => $city['longitude'] ?? null,
                ]);
            }
        }

        return $results->take($limit)->values();
    }

    private function searchDatabase(string $query, string $type, string $country, int $limit): ?Collection
    {
        if (! $this->canSearchDatabase()) {
            return null;
        }

        if ($type === 'country') {
            return LocationCity::query()
                ->select('country_code', 'country_name')
                ->when($query !== '', function ($builder) use ($query): void {
                    $builder->where(function ($nested) use ($query): void {
                        $nested
                            ->whereRaw('LOWER(country_code) = ?', [$query])
                            ->orWhereRaw('LOWER(country_name) LIKE ?', ['%'.$query.'%']);
                    });
                })
                ->whereNotNull('country_name')
                ->groupBy('country_code', 'country_name')
                ->orderBy('country_name')
                ->limit($limit)
                ->get()
                ->map(fn (LocationCity $city): array => [
                    'type' => 'country',
                    'value' => $city->country_name,
                    'label' => $city->country_name,
                    'country' => $city->country_name,
                    'country_code' => $city->country_code,
                ])
                ->values();
        }

        $cities = LocationCity::query()
            ->when($country !== '', function ($builder) use ($country): void {
                $builder->where(function ($nested) use ($country): void {
                    $nested
                        ->whereRaw('LOWER(country_code) = ?', [$country])
                        ->orWhereRaw('LOWER(country_name) = ?', [$country]);
                });
            })
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where('search_text', 'like', '%'.$query.'%');
            })
            ->orderByDesc('population')
            ->orderBy('ascii_name')
            ->limit($limit)
            ->get();

        return $cities
            ->map(fn (LocationCity $city): array => [
                'type' => 'city',
                'value' => $city->country_code.':'.$city->name,
                'label' => trim($city->name.', '.$city->admin1_name.' - '.$city->country_name, ' ,-'),
                'city' => $city->name,
                'region' => $city->admin1_name,
                'country' => $city->country_name,
                'country_code' => $city->country_code,
                'latitude' => $city->latitude,
                'longitude' => $city->longitude,
            ])
            ->values();
    }

    private function canSearchDatabase(): bool
    {
        try {
            return Schema::hasTable('location_cities') && LocationCity::query()->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function countries(): array
    {
        return config('locations.countries', []);
    }

    private function matches(string $query, string ...$values): bool
    {
        if ($query === '') {
            return true;
        }

        foreach ($values as $value) {
            if (str_contains($value, $query)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->ascii()->lower()->squish()->toString();
    }
}
