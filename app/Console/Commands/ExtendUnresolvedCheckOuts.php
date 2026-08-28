<?php

namespace App\Console\Commands;

use App\Models\CheckOutAlertEscalation;
use App\Models\Stay;
use App\Services\CheckIn\CheckInUpdateService;
use Illuminate\Console\Command;
use Throwable;

class ExtendUnresolvedCheckOuts extends Command
{
    protected $signature = 'checkouts:extend-unresolved';

    protected $description = 'Extiende una noche los check-outs pendientes o genera una alerta critica si existe conflicto';

    public function handle(CheckInUpdateService $updates): int
    {
        $extended = 0;
        $conflicts = 0;

        Stay::query()
            ->withoutGlobalScope('company')
            ->where('status', 'occupied')
            ->whereDate('check_out_date', today())
            ->with(['guests', 'accountStatement'])
            ->orderBy('id')
            ->chunkById(100, function ($stays) use ($updates, &$extended, &$conflicts): void {
                foreach ($stays as $stay) {
                    try {
                        $updates->updateStay($stay, [
                            'check_out_date' => $stay->check_out_date->addDay()->toDateString(),
                            'people_count' => $stay->people_count,
                            'price_per_night_bob' => $stay->price_per_night_bob,
                            'price_per_night_usd' => $stay->price_per_night_usd,
                            'exchange_rate' => $stay->exchange_rate,
                            'currency' => $stay->currency,
                            'breakfast_included' => $stay->breakfast_included,
                            'guests' => $stay->guests->map(fn ($guest): array => [
                                'id' => $guest->id,
                                'document_type' => $guest->document_type,
                                'document_number' => $guest->document_number,
                                'first_name' => $guest->first_name,
                                'last_name' => $guest->last_name,
                                'birth_date' => $guest->birth_date?->toDateString(),
                                'birth_country_id' => $guest->birth_country_id,
                            ])->all(),
                        ]);
                        CheckOutAlertEscalation::query()->where('stay_id', $stay->id)->delete();
                        $extended++;
                    } catch (Throwable $exception) {
                        CheckOutAlertEscalation::query()->updateOrCreate(
                            ['stay_id' => $stay->id],
                            ['company_id' => $stay->company_id, 'reason' => $exception->getMessage()],
                        );
                        $conflicts++;
                        report($exception);
                    }
                }
            });

        $this->info("Check-outs procesados: {$extended} extendidos y {$conflicts} con conflicto.");

        return self::SUCCESS;
    }
}
