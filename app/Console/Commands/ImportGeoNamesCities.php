<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

class ImportGeoNamesCities extends Command
{
    protected $signature = 'locations:import-geonames
        {--source= : Ruta local a cities1000.zip o cities1000.txt}
        {--country-info= : Ruta local a countryInfo.txt}
        {--admin1= : Ruta local a admin1CodesASCII.txt}
        {--fresh : Vaciar location_cities antes de importar}
        {--limit= : Limitar filas importadas, util para pruebas}';

    protected $description = 'Importa ciudades mundiales desde GeoNames cities1000 a location_cities.';

    private const CITIES_URL = 'https://download.geonames.org/export/dump/cities1000.zip';

    private const COUNTRY_INFO_URL = 'https://download.geonames.org/export/dump/countryInfo.txt';

    private const ADMIN1_URL = 'https://download.geonames.org/export/dump/admin1CodesASCII.txt';

    public function handle(): int
    {
        $directory = storage_path('app/geonames');
        $this->ensureDirectory($directory);

        $source = $this->option('source') ?: $directory.'/cities1000.zip';
        $countryInfo = $this->option('country-info') ?: $directory.'/countryInfo.txt';
        $admin1 = $this->option('admin1') ?: $directory.'/admin1CodesASCII.txt';

        $this->downloadIfMissing($source, self::CITIES_URL);
        $this->downloadIfMissing($countryInfo, self::COUNTRY_INFO_URL);
        $this->downloadIfMissing($admin1, self::ADMIN1_URL);

        $countries = $this->loadCountries($countryInfo);
        $admin1Names = $this->loadAdmin1Names($admin1);

        if ($this->option('fresh')) {
            DB::table('location_cities')->truncate();
        }

        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $imported = $this->importCities($source, $countries, $admin1Names, $limit);

        $this->info("Ciudades importadas/actualizadas: {$imported}");

        return self::SUCCESS;
    }

    private function ensureDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    private function downloadIfMissing(string $path, string $url): void
    {
        if (is_file($path)) {
            return;
        }

        $this->info("Descargando {$url}");

        $read = fopen($url, 'rb');
        $write = fopen($path, 'wb');

        if ($read === false || $write === false) {
            throw new \RuntimeException("No se pudo descargar {$url}");
        }

        stream_copy_to_stream($read, $write);
        fclose($read);
        fclose($write);
    }

    private function loadCountries(string $path): array
    {
        $countries = [];
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return $countries;
        }

        while (($line = fgets($handle)) !== false) {
            if (str_starts_with($line, '#')) {
                continue;
            }

            $columns = explode("\t", trim($line));

            if (count($columns) < 5) {
                continue;
            }

            $countries[$columns[0]] = $columns[4];
        }

        fclose($handle);

        return $countries;
    }

    private function loadAdmin1Names(string $path): array
    {
        $admin1Names = [];
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return $admin1Names;
        }

        while (($line = fgets($handle)) !== false) {
            $columns = explode("\t", trim($line));

            if (count($columns) < 3) {
                continue;
            }

            $admin1Names[$columns[0]] = $columns[2] ?: $columns[1];
        }

        fclose($handle);

        return $admin1Names;
    }

    private function importCities(string $source, array $countries, array $admin1Names, ?int $limit): int
    {
        $handle = $this->openCitiesSource($source);
        $rows = [];
        $imported = 0;
        $now = now();

        while (($line = fgets($handle)) !== false) {
            $columns = explode("\t", trim($line));

            if (count($columns) < 19) {
                continue;
            }

            $countryCode = $columns[8];
            $admin1Code = $columns[10] ?: null;
            $admin1Name = $admin1Code ? ($admin1Names[$countryCode.'.'.$admin1Code] ?? null) : null;
            $countryName = $countries[$countryCode] ?? $countryCode;

            $rows[] = [
                'geoname_id' => (int) $columns[0],
                'name' => $columns[1],
                'ascii_name' => $columns[2] ?: null,
                'alternate_names' => $columns[3] ?: null,
                'country_code' => $countryCode,
                'country_name' => $countryName,
                'admin1_code' => $admin1Code,
                'admin1_name' => $admin1Name,
                'latitude' => $columns[4] !== '' ? (float) $columns[4] : null,
                'longitude' => $columns[5] !== '' ? (float) $columns[5] : null,
                'population' => $columns[14] !== '' ? (int) $columns[14] : 0,
                'timezone' => $columns[17] ?: null,
                'feature_code' => $columns[7] ?: null,
                'search_text' => $this->normalizeSearchText([
                    $columns[1],
                    $columns[2],
                    $columns[3],
                    $admin1Name,
                    $countryName,
                    $countryCode,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($rows) >= 1000) {
                $imported += $this->upsertRows($rows);
                $rows = [];
            }

            if ($limit !== null && ($imported + count($rows)) >= $limit) {
                break;
            }
        }

        if ($rows !== []) {
            $imported += $this->upsertRows($rows);
        }

        if (is_resource($handle)) {
            fclose($handle);
        }

        return $limit !== null ? min($imported, $limit) : $imported;
    }

    /**
     * @return resource
     */
    private function openCitiesSource(string $source)
    {
        if (str_ends_with($source, '.txt')) {
            $handle = fopen($source, 'rb');

            if ($handle === false) {
                throw new \RuntimeException("No se pudo abrir {$source}");
            }

            return $handle;
        }

        $zip = new ZipArchive;

        if ($zip->open($source) !== true) {
            throw new \RuntimeException("No se pudo abrir {$source}");
        }

        $extractedPath = dirname($source).'/cities1000.txt';

        if (! is_file($extractedPath) && ! $zip->extractTo(dirname($source), 'cities1000.txt')) {
            $zip->close();

            throw new \RuntimeException('El zip no contiene cities1000.txt');
        }

        $zip->close();

        $handle = fopen($extractedPath, 'rb');

        if ($handle === false) {
            throw new \RuntimeException("No se pudo abrir {$extractedPath}");
        }

        return $handle;
    }

    private function upsertRows(array $rows): int
    {
        DB::table('location_cities')->upsert($rows, ['geoname_id'], [
            'name',
            'ascii_name',
            'alternate_names',
            'country_code',
            'country_name',
            'admin1_code',
            'admin1_name',
            'latitude',
            'longitude',
            'population',
            'timezone',
            'feature_code',
            'search_text',
            'updated_at',
        ]);

        return count($rows);
    }

    private function normalizeSearchText(array $parts): string
    {
        return Str::of(implode(' ', array_filter($parts)))
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
    }
}
