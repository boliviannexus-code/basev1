<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MarkPastReservationsAsNoShow extends Command
{
    protected $signature = 'reservations:mark-no-shows';

    protected $description = 'Proceso legado deshabilitado: los no show de reservas se resuelven manualmente';

    public function handle(): int
    {
        $this->warn('No show automatico deshabilitado. Use Casos sin atencion para cancelar o marcar no show manualmente.');

        return self::SUCCESS;
    }
}
