<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DatabaseBackupService
{
    private string $disk = 'local';

    private string $directory = 'database-backups';

    public function backups(): Collection
    {
        return collect(Storage::disk($this->disk)->files($this->directory))
            ->filter(fn (string $path): bool => Str::endsWith($path, ['.sql', '.sqlite']))
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
        $extension = $driver === 'sqlite' ? 'sqlite' : 'sql';
        $filename = sprintf(
            '%s_%s.%s',
            Str::slug((string) Config::get('app.name', 'nexgol')),
            now()->format('Ymd_His'),
            $extension
        );
        $path = Storage::disk($this->disk)->path("{$this->directory}/{$filename}");

        match ($driver) {
            'pgsql' => $this->dumpPostgres($connection, $path),
            'mysql', 'mariadb' => $this->dumpMysql($connection, $path),
            'sqlite' => $this->dumpSqlite($connection, $path),
            default => throw new RuntimeException("El motor de base de datos [{$driver}] no esta soportado para respaldos."),
        };

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

        match ($driver) {
            'pgsql' => $this->restorePostgres($connection, $path),
            'mysql', 'mariadb' => $this->restoreMysql($connection, $path),
            'sqlite' => $this->restoreSqlite($connection, $path),
            default => throw new RuntimeException("El motor de base de datos [{$driver}] no esta soportado para restauracion."),
        };
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
        $command = [
            'psql',
            '--host='.$connection['host'],
            '--port='.(string) $connection['port'],
            '--username='.$connection['username'],
            '--dbname='.$connection['database'],
            '--file='.$path,
        ];

        $this->run($command, ['PGPASSWORD' => (string) ($connection['password'] ?? '')]);
    }

    private function dumpMysql(array $connection, string $path): void
    {
        $command = [
            'mysqldump',
            '--host='.$connection['host'],
            '--port='.(string) $connection['port'],
            '--user='.$connection['username'],
            '--single-transaction',
            '--routines',
            '--triggers',
            '--result-file='.$path,
            $connection['database'],
        ];

        $this->run($command, ['MYSQL_PWD' => (string) ($connection['password'] ?? '')]);
    }

    private function restoreMysql(array $connection, string $path): void
    {
        $command = [
            'mysql',
            '--host='.$connection['host'],
            '--port='.(string) $connection['port'],
            '--user='.$connection['username'],
            $connection['database'],
        ];

        $this->run($command, ['MYSQL_PWD' => (string) ($connection['password'] ?? '')], File::get($path));
    }

    private function dumpSqlite(array $connection, string $path): void
    {
        File::copy($connection['database'], $path);
    }

    private function restoreSqlite(array $connection, string $path): void
    {
        File::copy($path, $connection['database']);
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

    private function normalizeBackupPath(string $backup): string
    {
        $name = basename($backup);

        if (! preg_match('/^[A-Za-z0-9._-]+\\.(sql|sqlite)$/', $name)) {
            throw new RuntimeException('Nombre de respaldo invalido.');
        }

        return "{$this->directory}/{$name}";
    }

    private function ensureDirectoryExists(): void
    {
        Storage::disk($this->disk)->makeDirectory($this->directory);
    }
}
