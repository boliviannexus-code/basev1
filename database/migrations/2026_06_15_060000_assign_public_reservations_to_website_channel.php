<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $companyIds = DB::table('reservation_groups')
            ->whereNull('reservation_channel_id')
            ->where(function ($query): void {
                $query
                    ->where('notes', 'Reserva creada desde el portal publico.')
                    ->orWhereExists(function ($query): void {
                        $query
                            ->selectRaw('1')
                            ->from('reservations')
                            ->whereColumn('reservations.reservation_group_id', 'reservation_groups.id')
                            ->whereNotNull('reservations.hold_expires_at');
                    });
            })
            ->distinct()
            ->pluck('company_id');

        foreach ($companyIds as $companyId) {
            $channelId = $this->websiteChannelId((int) $companyId);

            $groupIds = DB::table('reservation_groups')
                ->where('company_id', $companyId)
                ->whereNull('reservation_channel_id')
                ->where(function ($query): void {
                    $query
                        ->where('notes', 'Reserva creada desde el portal publico.')
                        ->orWhereExists(function ($query): void {
                            $query
                                ->selectRaw('1')
                                ->from('reservations')
                                ->whereColumn('reservations.reservation_group_id', 'reservation_groups.id')
                                ->whereNotNull('reservations.hold_expires_at');
                        });
                })
                ->pluck('id');

            DB::table('reservation_groups')
                ->whereIn('id', $groupIds)
                ->update([
                    'reservation_channel_id' => $channelId,
                    'updated_at' => now(),
                ]);

            DB::table('reservations')
                ->where('reservations.company_id', $companyId)
                ->whereIn('reservation_group_id', $groupIds)
                ->whereNull('reservations.reservation_channel_id')
                ->update([
                    'reservation_channel_id' => $channelId,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $channelIds = DB::table('reservation_channels')
            ->where('slug', 'pagina-web')
            ->pluck('id');

        DB::table('reservations')
            ->whereIn('reservation_channel_id', $channelIds)
            ->whereNotNull('hold_expires_at')
            ->update([
                'reservation_channel_id' => null,
                'updated_at' => now(),
            ]);

        DB::table('reservation_groups')
            ->whereIn('reservation_channel_id', $channelIds)
            ->where('notes', 'Reserva creada desde el portal publico.')
            ->update([
                'reservation_channel_id' => null,
                'updated_at' => now(),
            ]);
    }

    private function websiteChannelId(int $companyId): int
    {
        $now = now();
        $channel = DB::table('reservation_channels')
            ->where('company_id', $companyId)
            ->where('slug', 'pagina-web')
            ->first();

        if (! $channel) {
            return (int) DB::table('reservation_channels')->insertGetId([
                'company_id' => $companyId,
                'name' => 'Página web',
                'slug' => 'pagina-web',
                'type' => 'direct',
                'contact_name' => null,
                'contact_email' => null,
                'contact_phone' => null,
                'commission_percent' => null,
                'notes' => null,
                'is_active' => true,
                'is_protected' => true,
                'sort_order' => 0,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('reservation_channels')
            ->where('id', $channel->id)
            ->update([
                'name' => 'Página web',
                'type' => 'direct',
                'commission_percent' => null,
                'is_active' => true,
                'is_protected' => true,
                'sort_order' => 0,
                'deleted_at' => null,
                'updated_at' => $now,
            ]);

        return (int) $channel->id;
    }
};
