<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;

class DatabaseBackupService
{
    private const FORMAT = 'nido.database-backup.v1';
    private const SQL_FORMAT = 'nido.database-sql-backup.v1';
    private const DISK = 'local';
    private const DIRECTORY = 'private/database-backups';

    /**
     * @return array<string, mixed>
     */
    public function export(): array
    {
        $tables = $this->tables();
        $payloadTables = [];

        foreach ($tables as $table) {
            $rows = DB::table($table)
                ->orderBy($this->firstSortableColumn($table))
                ->get()
                ->map(fn (object $row): array => $this->normalizeRow((array) $row))
                ->all();

            $payloadTables[$table] = [
                'columns' => Schema::getColumnListing($table),
                'count' => count($rows),
                'checksum' => $this->checksum($rows),
                'rows' => $rows,
            ];
        }

        return [
            'format' => self::FORMAT,
            'generated_at' => now()->toIso8601String(),
            'connection' => DB::connection()->getDriverName(),
            'database' => (string) config('database.default'),
            'table_count' => count($tables),
            'tables' => $payloadTables,
        ];
    }

    public function exportJson(): string
    {
        return json_encode($this->export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function createSqlBackup(): string
    {
        $filename = 'respaldo-base-datos-'.now()->format('Ymd-His').'.sql';
        $path = self::DIRECTORY.'/'.$filename;

        Storage::disk(self::DISK)->put($path, $this->exportSql());

        return $filename;
    }

    /**
     * @return array<int, array{name: string, size: int, created_at: \Illuminate\Support\Carbon|null}>
     */
    public function listSqlBackups(): array
    {
        return collect(Storage::disk(self::DISK)->files(self::DIRECTORY))
            ->filter(fn (string $path): bool => str_ends_with($path, '.sql'))
            ->map(fn (string $path): array => [
                'name' => basename($path),
                'size' => Storage::disk(self::DISK)->size($path),
                'created_at' => rescue(fn () => now()->createFromTimestamp(Storage::disk(self::DISK)->lastModified($path)), null, false),
            ])
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }

    public function sqlBackupPath(string $filename): string
    {
        $path = $this->storedSqlPath($filename);

        if (! Storage::disk(self::DISK)->exists($path)) {
            throw ValidationException::withMessages([
                'backup' => 'El respaldo seleccionado no existe.',
            ]);
        }

        return Storage::disk(self::DISK)->path($path);
    }

    public function deleteSqlBackup(string $filename): void
    {
        $path = $this->storedSqlPath($filename);

        if (! Storage::disk(self::DISK)->exists($path)) {
            throw ValidationException::withMessages([
                'backup' => 'El respaldo seleccionado no existe.',
            ]);
        }

        Storage::disk(self::DISK)->delete($path);
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreStoredSql(string $filename): array
    {
        return $this->restoreSql((string) Storage::disk(self::DISK)->get($this->storedSqlPath($filename)));
    }

    /**
     * @return array<string, mixed>
     */
    public function restoreUploadedSql(UploadedFile $file): array
    {
        return $this->restoreSql((string) file_get_contents($file->getRealPath()));
    }

    public function exportSql(): string
    {
        $tables = $this->tables();
        $tablePayloads = [];

        foreach ($tables as $table) {
            $rows = DB::table($table)
                ->orderBy($this->firstSortableColumn($table))
                ->get()
                ->map(fn (object $row): array => $this->normalizeRow((array) $row))
                ->all();

            $tablePayloads[$table] = [
                'columns' => Schema::getColumnListing($table),
                'count' => count($rows),
                'checksum' => $this->checksum($rows),
                'rows' => $rows,
            ];
        }

        $lines = [
            '-- NIDO_BACKUP_FORMAT: '.self::SQL_FORMAT,
            '-- NIDO_BACKUP_GENERATED_AT: '.now()->toIso8601String(),
            '-- NIDO_BACKUP_CONNECTION: '.DB::connection()->getDriverName(),
            '-- NIDO_BACKUP_TABLE_COUNT: '.count($tables),
        ];

        foreach ($tablePayloads as $table => $payload) {
            $lines[] = sprintf(
                '-- NIDO_BACKUP_TABLE: %s %d %s',
                $table,
                $payload['count'],
                $payload['checksum'],
            );
        }

        $lines[] = '';
        $lines[] = $this->truncateSql($tables);

        foreach ($this->tablesForInsert() as $table) {
            $payload = $tablePayloads[$table];
            $columns = $payload['columns'];
            $quotedColumns = collect($columns)
                ->map(fn (string $column): string => $this->quoteIdentifier($column))
                ->implode(', ');

            foreach (array_chunk($payload['rows'], 250) as $chunk) {
                if ($chunk === []) {
                    continue;
                }

                $values = collect($chunk)
                    ->map(fn (array $row): string => '('.collect($columns)
                        ->map(fn (string $column): string => $this->quoteValue($row[$column] ?? null))
                        ->implode(', ').')')
                    ->implode(",\n");

                $lines[] = 'INSERT INTO '.$this->quoteIdentifier($table).' ('.$quotedColumns.") VALUES\n".$values.';';
            }
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<string, mixed>
     */
    public function readUploadedBackup(UploadedFile $file): array
    {
        try {
            $payload = json_decode((string) file_get_contents($file->getRealPath()), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'backup' => 'El archivo no contiene un respaldo JSON valido.',
            ]);
        }

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'backup' => 'El archivo de respaldo esta vacio o tiene una estructura invalida.',
            ]);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function validatePayload(array $payload): array
    {
        $errors = [];

        if (($payload['format'] ?? null) !== self::FORMAT) {
            $errors[] = 'El formato del respaldo no corresponde a esta version del sistema.';
        }

        if (! isset($payload['tables']) || ! is_array($payload['tables'])) {
            $errors[] = 'El respaldo no contiene tablas exportadas.';
        }

        $currentTables = $this->tables();
        $payloadTables = array_keys((array) ($payload['tables'] ?? []));
        $missingTables = array_diff($currentTables, $payloadTables);
        $unknownTables = array_diff($payloadTables, $currentTables);

        foreach ($missingTables as $table) {
            $errors[] = "Falta la tabla {$table} en el respaldo.";
        }

        foreach ($unknownTables as $table) {
            $errors[] = "La tabla {$table} no existe en esta base de datos.";
        }

        foreach ((array) ($payload['tables'] ?? []) as $table => $tablePayload) {
            if (! is_array($tablePayload)) {
                $errors[] = "La tabla {$table} tiene una estructura invalida.";
                continue;
            }

            $rows = Arr::get($tablePayload, 'rows');
            if (! is_array($rows)) {
                $errors[] = "La tabla {$table} no contiene filas exportadas.";
                continue;
            }

            $expectedColumns = Schema::hasTable((string) $table) ? Schema::getColumnListing((string) $table) : [];
            $columns = Arr::get($tablePayload, 'columns', []);
            $missingColumns = array_diff($expectedColumns, is_array($columns) ? $columns : []);

            foreach ($missingColumns as $column) {
                $errors[] = "Falta la columna {$table}.{$column} en el respaldo.";
            }

            if ((int) Arr::get($tablePayload, 'count', -1) !== count($rows)) {
                $errors[] = "El conteo de filas de {$table} no coincide con las filas exportadas.";
            }

            if ((string) Arr::get($tablePayload, 'checksum', '') !== $this->checksum($rows)) {
                $errors[] = "El checksum de {$table} no coincide; el respaldo puede estar incompleto o alterado.";
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'table_count' => count($payloadTables),
            'row_count' => collect((array) ($payload['tables'] ?? []))->sum(fn (mixed $table): int => is_array($table) ? count((array) ($table['rows'] ?? [])) : 0),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function restore(array $payload): array
    {
        $validation = $this->validatePayload($payload);

        if (! $validation['valid']) {
            throw ValidationException::withMessages(['backup' => $validation['errors']]);
        }

        DB::transaction(function () use ($payload): void {
            Schema::disableForeignKeyConstraints();

            try {
                $this->clearTablesForRestore();

                foreach ($this->tablesForInsert() as $table) {
                    $tablePayload = $payload['tables'][$table];

                    foreach (array_chunk((array) $tablePayload['rows'], 500) as $chunk) {
                        if ($chunk !== []) {
                            DB::table((string) $table)->insert($chunk);
                        }
                    }
                }
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        });

        $postRestore = $this->validateCurrentDatabaseAgainst($payload);

        if (! $postRestore['valid']) {
            throw ValidationException::withMessages(['backup' => $postRestore['errors']]);
        }

        return $postRestore;
    }

    /**
     * @return array<string, mixed>
     */
    private function restoreSql(string $sql): array
    {
        $sql = trim($sql);

        if ($sql === '') {
            throw ValidationException::withMessages([
                'backup' => 'El archivo SQL esta vacio.',
            ]);
        }

        $metadata = $this->parseSqlMetadata($sql);

        DB::transaction(function () use ($sql): void {
            DB::unprepared($sql);
        });

        if ($metadata !== null) {
            $postRestore = $this->validateCurrentDatabaseAgainstSqlMetadata($metadata);

            if (! $postRestore['valid']) {
                throw ValidationException::withMessages(['backup' => $postRestore['errors']]);
            }

            return $postRestore;
        }

        return [
            'valid' => true,
            'errors' => [],
            'table_count' => null,
            'row_count' => null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function validateCurrentDatabaseAgainst(array $payload): array
    {
        $errors = [];

        foreach ($payload['tables'] as $table => $tablePayload) {
            $rows = DB::table((string) $table)
                ->orderBy($this->firstSortableColumn((string) $table))
                ->get()
                ->map(fn (object $row): array => $this->normalizeRow((array) $row))
                ->all();

            if (count($rows) !== (int) $tablePayload['count']) {
                $errors[] = "La tabla {$table} se restauro incompleta.";
            }

            if ($this->checksum($rows) !== (string) $tablePayload['checksum']) {
                $errors[] = "La tabla {$table} no coincide despues de restaurar.";
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'table_count' => count($payload['tables']),
            'row_count' => collect($payload['tables'])->sum(fn (array $table): int => (int) $table['count']),
        ];
    }

    /**
     * @param array<string, array{count: int, checksum: string}> $metadata
     * @return array<string, mixed>
     */
    private function validateCurrentDatabaseAgainstSqlMetadata(array $metadata): array
    {
        $errors = [];

        foreach ($metadata as $table => $tableMetadata) {
            if (! Schema::hasTable($table)) {
                $errors[] = "La tabla {$table} no existe despues de restaurar.";
                continue;
            }

            $rows = DB::table($table)
                ->orderBy($this->firstSortableColumn($table))
                ->get()
                ->map(fn (object $row): array => $this->normalizeRow((array) $row))
                ->all();

            if (count($rows) !== $tableMetadata['count']) {
                $errors[] = "La tabla {$table} se restauro incompleta.";
            }

            if ($this->checksum($rows) !== $tableMetadata['checksum']) {
                $errors[] = "La tabla {$table} no coincide despues de restaurar.";
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'table_count' => count($metadata),
            'row_count' => collect($metadata)->sum(fn (array $table): int => $table['count']),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function tables(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        $tables = match ($driver) {
            'sqlite' => collect($connection->select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%'"))
                ->pluck('name'),
            'pgsql' => collect($connection->select("select tablename as name from pg_tables where schemaname = current_schema()"))
                ->pluck('name'),
            'mysql', 'mariadb' => collect($connection->select('show full tables where Table_type = "BASE TABLE"'))
                ->map(fn (object $row): string => (string) array_values((array) $row)[0]),
            default => collect(Schema::getTables())->pluck('name'),
        };

        return $tables
            ->reject(fn (string $table): bool => str_starts_with($table, 'telescope_'))
            ->sort()
            ->values()
            ->all();
    }

    private function firstSortableColumn(string $table): string
    {
        return Schema::getColumnListing($table)[0] ?? 'id';
    }

    private function clearTablesForRestore(): void
    {
        $tables = $this->tables();

        if (DB::connection()->getDriverName() === 'pgsql') {
            $quotedTables = collect($tables)
                ->map(fn (string $table): string => '"'.str_replace('"', '""', $table).'"')
                ->implode(', ');

            DB::statement("TRUNCATE TABLE {$quotedTables} RESTART IDENTITY CASCADE");

            return;
        }

        foreach (array_reverse($tables) as $table) {
            DB::table($table)->delete();
        }
    }

    /**
     * @param array<int, string> $tables
     */
    private function truncateSql(array $tables): string
    {
        $quotedTables = collect($tables)
            ->map(fn (string $table): string => $this->quoteIdentifier($table))
            ->all();

        return match (DB::connection()->getDriverName()) {
            'pgsql' => 'TRUNCATE TABLE '.implode(', ', $quotedTables).' RESTART IDENTITY CASCADE;',
            'mysql', 'mariadb' => "SET FOREIGN_KEY_CHECKS=0;\n"
                .collect($quotedTables)->reverse()->map(fn (string $table): string => "DELETE FROM {$table};")->implode("\n")
                ."\nSET FOREIGN_KEY_CHECKS=1;",
            default => collect($quotedTables)->reverse()->map(fn (string $table): string => "DELETE FROM {$table};")->implode("\n"),
        };
    }

    /**
     * @return array<int, string>
     */
    private function tablesForInsert(): array
    {
        $tables = $this->tables();
        $dependencies = array_fill_keys($tables, []);

        foreach ($this->foreignKeys() as $foreignKey) {
            if (isset($dependencies[$foreignKey['table']]) && in_array($foreignKey['parent'], $tables, true)) {
                $dependencies[$foreignKey['table']][] = $foreignKey['parent'];
            }
        }

        $ordered = [];

        while (count($ordered) < count($tables)) {
            $progress = false;

            foreach ($dependencies as $table => $parents) {
                if (in_array($table, $ordered, true)) {
                    continue;
                }

                if (array_diff($parents, $ordered) === []) {
                    $ordered[] = $table;
                    $progress = true;
                }
            }

            if (! $progress) {
                return $tables;
            }
        }

        return $ordered;
    }

    /**
     * @return array<int, array{table: string, parent: string}>
     */
    private function foreignKeys(): array
    {
        $connection = DB::connection();

        return match ($connection->getDriverName()) {
            'pgsql' => collect($connection->select(
                "select tc.table_name as table, ccu.table_name as parent
                from information_schema.table_constraints tc
                join information_schema.constraint_column_usage ccu
                    on ccu.constraint_name = tc.constraint_name
                    and ccu.constraint_schema = tc.constraint_schema
                where tc.constraint_type = 'FOREIGN KEY'
                    and tc.table_schema = current_schema()"
            ))->map(fn (object $row): array => ['table' => $row->table, 'parent' => $row->parent])->all(),
            'sqlite' => collect($this->tables())->flatMap(function (string $table) use ($connection): array {
                return collect($connection->select("pragma foreign_key_list('{$table}')"))
                    ->map(fn (object $row): array => ['table' => $table, 'parent' => $row->table])
                    ->all();
            })->all(),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        ksort($row);

        return array_map(static function (mixed $value): mixed {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            return is_resource($value) ? stream_get_contents($value) : $value;
        }, $row);
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return '`'.str_replace('`', '``', $identifier).'`';
        }

        return '"'.str_replace('"', '""', $identifier).'"';
    }

    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        return DB::connection()->getPdo()->quote((string) $value);
    }

    private function storedSqlPath(string $filename): string
    {
        if (! preg_match('/\A[\w.-]+\.sql\z/', $filename)) {
            throw ValidationException::withMessages([
                'backup' => 'El nombre del respaldo no es valido.',
            ]);
        }

        return self::DIRECTORY.'/'.$filename;
    }

    /**
     * @return array<string, array{count: int, checksum: string}>|null
     */
    private function parseSqlMetadata(string $sql): ?array
    {
        if (! str_contains($sql, '-- NIDO_BACKUP_FORMAT: '.self::SQL_FORMAT)) {
            return null;
        }

        preg_match_all('/^-- NIDO_BACKUP_TABLE:\s+(\S+)\s+(\d+)\s+([a-f0-9]{64})$/m', $sql, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            throw ValidationException::withMessages([
                'backup' => 'El respaldo SQL generado por el sistema no contiene metadatos de validacion.',
            ]);
        }

        $metadata = [];

        foreach ($matches as $match) {
            $metadata[$match[1]] = [
                'count' => (int) $match[2],
                'checksum' => $match[3],
            ];
        }

        return $metadata;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function checksum(array $rows): string
    {
        usort($rows, static function (array $left, array $right): int {
            return json_encode($left, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                <=> json_encode($right, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        });

        return hash('sha256', json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }
}
