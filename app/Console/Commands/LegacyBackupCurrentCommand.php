<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class LegacyBackupCurrentCommand extends Command
{
    protected $signature = 'legacy:backup-current {--path=storage/app/backups : Directorio destino del backup}';

    protected $description = 'Genera un backup pg_dump de la base actual antes de una importacion legacy.';

    public function handle(): int
    {
        $path = base_path((string) $this->option('path'));
        File::ensureDirectoryExists($path);

        $filename = $path.'/nexgol-backup-'.now()->format('Ymd-His').'.sql';
        $host = config('database.connections.pgsql.host');
        $port = config('database.connections.pgsql.port');
        $database = config('database.connections.pgsql.database');
        $username = config('database.connections.pgsql.username');
        $password = config('database.connections.pgsql.password');

        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s --clean --if-exists --no-owner --no-acl > %s',
            escapeshellarg((string) $password),
            escapeshellarg((string) $host),
            escapeshellarg((string) $port),
            escapeshellarg((string) $username),
            escapeshellarg((string) $database),
            escapeshellarg($filename)
        );

        $this->info('Ejecutando pg_dump...');
        $result = null;
        system($command, $result);

        if ($result !== 0) {
            $this->error('No se pudo crear el backup. Verifica que pg_dump este disponible en el contenedor.');

            return self::FAILURE;
        }

        $this->info('Backup creado: '.$filename);

        return self::SUCCESS;
    }
}
