<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ExtendUnresolvedCheckOuts extends Command
{
    protected $signature = 'checkouts:extend-unresolved';

    protected $description = 'Comando desactivado: las noches adicionales requieren una decision manual';

    public function handle(): int
    {
        $this->warn('La extension automatica esta desactivada. Resuelva las estancias manualmente.');

        return self::SUCCESS;
    }
}
