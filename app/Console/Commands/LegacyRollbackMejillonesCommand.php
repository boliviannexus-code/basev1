<?php

namespace App\Console\Commands;

use App\Services\Legacy\MejillonesLegacyImportService;
use Illuminate\Console\Command;

class LegacyRollbackMejillonesCommand extends Command
{
    protected $signature = 'legacy:rollback-mejillones {--batch= : ID del lote legacy_import_batches}';

    protected $description = 'Revierte registros creados por un lote de importacion Mejillones.';

    public function handle(MejillonesLegacyImportService $service): int
    {
        $batchId = (int) $this->option('batch');

        if ($batchId <= 0) {
            $this->error('Debes indicar --batch=ID.');

            return self::FAILURE;
        }

        $batch = $service->rollback($batchId);

        $this->info('Rollback completado.');
        $this->line('Batch: '.$batch->id);
        $this->line('Estado: '.$batch->status);

        return self::SUCCESS;
    }
}
