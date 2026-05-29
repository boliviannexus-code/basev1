<?php

namespace App\Console\Commands;

use App\Services\Legacy\MejillonesLegacyImportService;
use Illuminate\Console\Command;

class LegacyImportMejillonesCommand extends Command
{
    protected $signature = 'legacy:import-mejillones
        {--company-id= : ID de la liga/empresa destino}
        {--dump= : Ruta del dump usado como fuente}
        {--dry-run : Simula y genera reporte sin escribir datos de dominio}
        {--commit : Ejecuta la importacion real}';

    protected $description = 'Importa datos legacy Mejillones hacia NexGol con auditoria.';

    public function handle(MejillonesLegacyImportService $service): int
    {
        $companyId = (int) $this->option('company-id');

        if ($companyId <= 0) {
            $this->error('Debes indicar --company-id=ID.');

            return self::FAILURE;
        }

        if ($this->option('commit') && $this->option('dry-run')) {
            $this->error('Usa solo una opcion: --dry-run o --commit.');

            return self::FAILURE;
        }

        if (! $this->option('commit')) {
            $batch = $service->dryRun($companyId, $this->option('dump'));
            $this->info('Dry-run completado. No se escribieron datos de dominio.');
        } else {
            $batch = $service->import($companyId, $this->option('dump'));
            $this->info('Importacion completada.');
        }

        $this->line('Batch: '.$batch->id);
        $this->line('Estado: '.$batch->status);
        $this->line('Reporte: storage/app/private/'.$batch->report_path);

        return self::SUCCESS;
    }
}
