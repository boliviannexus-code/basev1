<?php

namespace App\Console\Commands;

use App\Services\Legacy\MejillonesLegacyImportService;
use Illuminate\Console\Command;

class LegacyAnalyzeMejillonesCommand extends Command
{
    protected $signature = 'legacy:analyze-mejillones {--company-id= : ID de la liga/empresa destino} {--dump= : Ruta del dump usado como fuente}';

    protected $description = 'Analiza la base legacy Mejillones restaurada en la conexion legacy.';

    public function handle(MejillonesLegacyImportService $service): int
    {
        $companyId = (int) $this->option('company-id');

        if ($companyId <= 0) {
            $this->error('Debes indicar --company-id=ID.');

            return self::FAILURE;
        }

        $batch = $service->analyze($companyId, $this->option('dump'));

        $this->info('Analisis completado.');
        $this->line('Batch: '.$batch->id);
        $this->line('Reporte: storage/app/private/'.$batch->report_path);
        $this->table(['Tabla', 'Registros'], collect($batch->summary['counts'] ?? [])->map(fn ($count, $table): array => [$table, $count])->values()->all());

        return self::SUCCESS;
    }
}
