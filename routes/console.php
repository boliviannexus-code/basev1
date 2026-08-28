<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::command('reservations:mark-no-shows')
    ->dailyAt('00:05')
    ->withoutOverlapping();

Schedule::command('checkouts:extend-unresolved')
    ->dailyAt('20:00')
    ->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('db:reset-sequences {--table= : Reset only one table sequence}', function (): int {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->error('Este comando solo aplica para PostgreSQL.');

        return self::FAILURE;
    }

    $tableFilter = $this->option('table');
    $bindings = [];
    $filterSql = '';

    if ($tableFilter) {
        $filterSql = 'and tbl.relname = ?';
        $bindings[] = $tableFilter;
    }

    $sequences = DB::select(
        "select
            tbl.relname as table_name,
            col.attname as column_name,
            seq.relname as sequence_name
        from pg_class seq
        join pg_depend dep on dep.objid = seq.oid
        join pg_class tbl on dep.refobjid = tbl.oid
        join pg_attribute col on col.attrelid = tbl.oid and col.attnum = dep.refobjsubid
        join pg_namespace ns on ns.oid = tbl.relnamespace
        where seq.relkind = 'S'
            and ns.nspname = current_schema()
            {$filterSql}
        order by tbl.relname, col.attname",
        $bindings,
    );

    if ($sequences === []) {
        $this->warn('No se encontraron secuencias para resetear.');

        return self::SUCCESS;
    }

    foreach ($sequences as $sequence) {
        $table = (string) $sequence->table_name;
        $column = (string) $sequence->column_name;
        $quotedTable = '"'.str_replace('"', '""', $table).'"';
        $quotedColumn = '"'.str_replace('"', '""', $column).'"';
        $tableLiteral = str_replace("'", "''", $table);
        $columnLiteral = str_replace("'", "''", $column);

        DB::statement(
            "select setval(
                pg_get_serial_sequence('{$tableLiteral}', '{$columnLiteral}'),
                coalesce((select max({$quotedColumn}) from {$quotedTable}), 0) + 1,
                false
            )"
        );

        $this->line("OK {$table}.{$column}");
    }

    $this->info('Secuencias reseteadas correctamente.');

    return self::SUCCESS;
})->purpose('Reset PostgreSQL sequences to the current max id plus one');
