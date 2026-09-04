<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DatabaseBackupService
{
    private const MANIFEST_PREFIX = '-- NEXGOL_BACKUP_MANIFEST: ';

    private string $disk = 'local';

    private string $directory = 'database-backups';

    public function backups(): Collection
    {
        return collect(Storage::disk($this->disk)->files($this->directory))
            ->filter(fn (string $path): bool => Str::endsWith($path, '.sql'))
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'path' => $path,
                'size' => Storage::disk($this->disk)->size($path),
                'last_modified' => Carbon::createFromTimestamp(Storage::disk($this->disk)->lastModified($path)),
            ])
            ->sortByDesc('last_modified')
            ->values();
    }

    public function create(): string
    {
        $this->ensureDirectoryExists();

        $connectionName = (string) Config::get('database.default');
        $connection = Config::get("database.connections.{$connectionName}", []);
        $driver = (string) ($connection['driver'] ?? '');

        if ($driver !== 'pgsql') {
            throw new RuntimeException('Los respaldos requieren una conexion PostgreSQL.');
        }

        $filename = sprintf(
            '%s_%s.sql',
            Str::slug((string) Config::get('app.name', 'nexgol')),
            now()->format('Ymd_His')
        );
        $path = Storage::disk($this->disk)->path("{$this->directory}/{$filename}");

        $this->dumpPostgres($connection, $path);

        $this->prependManifest($path, $driver);

        return "{$this->directory}/{$filename}";
    }

    public function absolutePath(string $backup): string
    {
        $path = $this->normalizeBackupPath($backup);

        if (! Storage::disk($this->disk)->exists($path)) {
            throw new RuntimeException('El respaldo solicitado no existe.');
        }

        return Storage::disk($this->disk)->path($path);
    }

    public function delete(string $backup): void
    {
        Storage::disk($this->disk)->delete($this->normalizeBackupPath($backup));
    }

    public function restoreFromBackup(string $backup): void
    {
        $this->restoreFromPath($this->absolutePath($backup));
    }

    public function restoreFromUpload(UploadedFile $file): void
    {
        $this->ensureDirectoryExists();

        $stored = $file->storeAs($this->directory.'/uploads', 'restore_'.now()->format('Ymd_His').'.sql', $this->disk);

        try {
            $this->restoreFromPath(Storage::disk($this->disk)->path($stored));
        } finally {
            Storage::disk($this->disk)->delete($stored);
        }
    }

    private function restoreFromPath(string $path): void
    {
        $connectionName = (string) Config::get('database.default');
        $connection = Config::get("database.connections.{$connectionName}", []);
        $driver = (string) ($connection['driver'] ?? '');
        $manifest = $this->manifestFromBackup($path);

        if ($driver !== 'pgsql') {
            throw new RuntimeException('La restauracion requiere una conexion PostgreSQL.');
        }

        $this->restorePostgres($connection, $path);

        $this->verifyRestoredCounts($manifest);
    }

    private function dumpPostgres(array $connection, string $path): void
    {
        $command = [
            'pg_dump',
            '--host='.$connection['host'],
            '--port='.(string) $connection['port'],
            '--username='.$connection['username'],
            '--format=plain',
            '--clean',
            '--if-exists',
            '--no-owner',
            '--no-privileges',
            '--file='.$path,
            $connection['database'],
        ];

        $this->run($command, ['PGPASSWORD' => (string) ($connection['password'] ?? '')]);
    }

    private function restorePostgres(array $connection, string $path): void
    {
        $this->resetPostgresSchema($connection);

        $command = [
            'psql',
            '--host='.$connection['host'],
            '--port='.(string) $connection['port'],
            '--username='.$connection['username'],
            '--dbname='.$connection['database'],
            '--single-transaction',
            '--set=ON_ERROR_STOP=1',
            '--file='.$path,
        ];

        $this->run($command, ['PGPASSWORD' => (string) ($connection['password'] ?? '')]);
    }

    private function resetPostgresSchema(array $connection): void
    {
        $command = [
            'psql',
            '--host='.$connection['host'],
            '--port='.(string) $connection['port'],
            '--username='.$connection['username'],
            '--dbname='.$connection['database'],
            '--set=ON_ERROR_STOP=1',
            '--command=DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;',
        ];

        $this->run($command, ['PGPASSWORD' => (string) ($connection['password'] ?? '')]);
    }

    private function run(array $command, array $env = [], ?string $input = null): void
    {
        $result = Process::timeout(600)
            ->env($env)
            ->input($input)
            ->run($command);

        if ($result->failed()) {
            throw new RuntimeException(trim($result->errorOutput()) ?: 'No se pudo completar la operacion de base de datos.');
        }
    }

    private function prependManifest(string $path, string $driver): void
    {
        $manifest = [
            'driver' => $driver,
            'created_at' => now()->toIso8601String(),
            'tables' => $this->tableCounts($driver),
        ];
        $encoded = base64_encode((string) json_encode($manifest, JSON_THROW_ON_ERROR));
        $temporaryPath = $path.'.tmp';
        $source = fopen($path, 'rb');
        $target = fopen($temporaryPath, 'wb');

        if (! $source || ! $target) {
            throw new RuntimeException('No se pudo preparar el manifiesto del respaldo.');
        }

        fwrite($target, self::MANIFEST_PREFIX.$encoded.PHP_EOL);
        stream_copy_to_stream($source, $target);
        fclose($source);
        fclose($target);

        File::move($temporaryPath, $path);
    }

    private function manifestFromBackup(string $path): ?array
    {
        $handle = fopen($path, 'rb');

        if (! $handle) {
            throw new RuntimeException('No se pudo abrir el respaldo para verificarlo.');
        }

        $firstLine = fgets($handle) ?: '';
        fclose($handle);

        if (! str_starts_with($firstLine, self::MANIFEST_PREFIX)) {
            return null;
        }

        $encoded = trim(substr($firstLine, strlen(self::MANIFEST_PREFIX)));
        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            throw new RuntimeException('El manifiesto del respaldo no es valido.');
        }

        $manifest = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);

        return is_array($manifest) ? $manifest : null;
    }

    private function verifyRestoredCounts(?array $manifest): void
    {
        if (! isset($manifest['tables']) || ! is_array($manifest['tables'])) {
            return;
        }

        $errors = [];

        foreach ($manifest['tables'] as $table => $expectedCount) {
            if (! is_string($table) || ! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
                throw new RuntimeException('El manifiesto del respaldo contiene una tabla invalida.');
            }

            try {
                $actualCount = DB::table($table)->count();
            } catch (Throwable) {
                $errors[] = "{$table}: tabla no restaurada";

                continue;
            }

            if ((int) $actualCount !== (int) $expectedCount) {
                $errors[] = "{$table}: esperado {$expectedCount}, restaurado {$actualCount}";
            }
        }

        if ($errors !== []) {
            throw new RuntimeException('La restauracion no coincide con el respaldo. '.implode('; ', $errors));
        }
    }

    private function tableCounts(string $driver): array
    {
        $tables = collect(DB::select(
            "select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE' order by table_name"
        ))->pluck('table_name')->all();

        return collect($tables)
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])
            ->all();
    }

    private function normalizeBackupPath(string $backup): string
    {
        $name = basename($backup);

        if (! preg_match('/^[A-Za-z0-9._-]+\\.sql$/', $name)) {
            throw new RuntimeException('Nombre de respaldo invalido.');
        }

        return "{$this->directory}/{$name}";
    }

    private function ensureDirectoryExists(): void
    {
        Storage::disk($this->disk)->makeDirectory($this->directory);
    }
}
