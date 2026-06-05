<?php

namespace App\Services\Availability;

use App\Models\AvailabilityDay;
use App\Models\OccupancyBlock;
use App\Models\Space;
use App\Models\SpaceRoom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AvailabilityGridService
{
    public const STATUS_META = [
        'available' => ['label' => 'Disponible', 'tone' => 'available'],
        'closed' => ['label' => 'Cerrado', 'tone' => 'closed'],
        'sold_out' => ['label' => 'Agotado', 'tone' => 'sold-out'],
    ];

    public function gridData(int $companyId, array $filters): array
    {
        $startDate = $this->startDate($filters['start_date'] ?? null);
        $endDate = $startDate->copy()->addDays(29);
        $dates = $this->dates($startDate);
        $spaces = $this->spaces($companyId, $filters);
        $availability = $this->availability($companyId, $startDate, $endDate, $filters);
        $blocks = $this->blocks($companyId, $startDate, $endDate, $filters);
        $rows = $this->rows($spaces, $dates, $availability, $blocks, $filters);

        return [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'previous_period' => $startDate->copy()->subDay()->toDateString(),
            'current_period' => now()->startOfDay()->toDateString(),
            'next_period' => $startDate->copy()->addDay()->toDateString(),
            'dates' => $dates,
            'rows' => $rows,
            'summary' => $this->summary($rows),
        ];
    }

    public function spacesForFilters(int $companyId): Collection
    {
        return Space::query()
            ->with([
                'spaceMode',
                'rooms' => fn ($query) => $query
                    ->with('beds.bedType')
                    ->where('status', 'active')
                    ->orderBy('name'),
            ])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereHas('spaceMode', fn (Builder $query): Builder => $query->whereIn('slug', ['privado', 'compartido']))
            ->orderByRaw('coalesce(title, name) asc')
            ->get();
    }

    public function saveDay(int $companyId, array $data): AvailabilityDay
    {
        [$space, $room] = $this->validateResource($companyId, $data['space_id'], $data['space_room_id'] ?? null);
        $price = $data['price'] ?? null;
        $status = $data['status'];

        if ($status === 'available' && $price === null) {
            throw ValidationException::withMessages([
                'price' => 'Asigna un precio antes de habilitar la fecha.',
            ]);
        }

        if ($price === null) {
            $status = 'closed';
        }

        return AvailabilityDay::query()->updateOrCreate(
            [
                'company_id' => $companyId,
                'space_id' => $space->id,
                'space_room_id' => $room?->id,
                'date' => Carbon::parse($data['date'])->toDateString(),
            ],
            [
                'price' => $price,
                'status' => $status,
            ],
        );
    }

    public function bulkUpdate(int $companyId, array $data): int
    {
        $resources = $this->resourcesForBulk($companyId, $data);
        $dates = collect(Carbon::parse($data['start_date'])->daysUntil(Carbon::parse($data['end_date'])->addDay()))
            ->map(fn (Carbon $date): string => $date->toDateString());
        $values = [];
        $explicitAvailable = (bool) ($data['apply_status'] ?? false) && ($data['status'] ?? null) === 'available';

        if ((bool) ($data['apply_price'] ?? false)) {
            $values['price'] = $data['price'] ?? null;
        }

        if ((bool) ($data['apply_status'] ?? false)) {
            $values['status'] = $data['status'];
        }

        $updates = [];

        foreach ($resources as $resource) {
            foreach ($dates as $date) {
                $existing = AvailabilityDay::query()
                    ->where('company_id', $companyId)
                    ->where('space_id', $resource['space_id'])
                    ->where('space_room_id', $resource['room_id'])
                    ->where('date', $date)
                    ->first();
                $price = array_key_exists('price', $values) ? $values['price'] : $existing?->price;
                $status = array_key_exists('status', $values) ? $values['status'] : ($existing?->status ?? 'closed');

                if ($price === null) {
                    $status = 'closed';
                }

                if ($explicitAvailable && $price === null) {
                    throw ValidationException::withMessages([
                        'price' => 'Asigna un precio antes de habilitar fechas en bloque.',
                    ]);
                }

                $updates[] = [
                    'keys' => [
                        'company_id' => $companyId,
                        'space_id' => $resource['space_id'],
                        'space_room_id' => $resource['room_id'],
                        'date' => $date,
                    ],
                    'values' => [
                        'price' => $price,
                        'status' => $status,
                    ],
                ];
            }
        }

        return DB::transaction(function () use ($updates): int {
            foreach ($updates as $update) {
                AvailabilityDay::query()->updateOrCreate($update['keys'], $update['values']);
            }

            return count($updates);
        });
    }

    private function startDate(?string $date): Carbon
    {
        return filled($date)
            ? Carbon::parse($date)->startOfDay()
            : now()->startOfDay();
    }

    private function dates(Carbon $startDate): array
    {
        $dayNames = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'];

        return collect(range(0, 29))
            ->map(function (int $offset) use ($startDate, $dayNames): array {
                $date = $startDate->copy()->addDays($offset);

                return [
                    'date' => $date->toDateString(),
                    'label' => $dayNames[$date->dayOfWeekIso - 1].' '.$date->format('d'),
                    'iso_day' => $date->dayOfWeekIso,
                    'is_today' => $date->isToday(),
                ];
            })
            ->all();
    }

    private function spaces(int $companyId, array $filters): Collection
    {
        return $this->spacesForFilters($companyId)
            ->filter(function (Space $space) use ($filters): bool {
                if (filled($filters['space_id'] ?? null) && (int) $filters['space_id'] !== (int) $space->id) {
                    return false;
                }

                $mode = $space->spaceMode?->slug;

                return match ($filters['type'] ?? 'all') {
                    'private' => $mode === 'privado',
                    'shared' => $mode === 'compartido',
                    default => true,
                };
            })
            ->values();
    }

    private function availability(int $companyId, Carbon $startDate, Carbon $endDate, array $filters): Collection
    {
        return AvailabilityDay::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when(filled($filters['space_id'] ?? null), fn (Builder $query): Builder => $query->where('space_id', $filters['space_id']))
            ->get()
            ->keyBy(fn (AvailabilityDay $day): string => $this->resourceKey((int) $day->space_id, $day->space_room_id ? (int) $day->space_room_id : null, $day->date->toDateString()));
    }

    private function blocks(int $companyId, Carbon $startDate, Carbon $endDate, array $filters): Collection
    {
        return OccupancyBlock::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('start_date', '<=', $endDate->toDateString())
            ->where('end_date', '>=', $startDate->toDateString())
            ->when(filled($filters['space_id'] ?? null), fn (Builder $query): Builder => $query->where('space_id', $filters['space_id']))
            ->get();
    }

    private function rows(Collection $spaces, array $dates, Collection $availability, Collection $blocks, array $filters): array
    {
        return $spaces
            ->flatMap(function (Space $space) use ($dates, $availability, $blocks, $filters): array {
                if ($space->spaceMode?->slug === 'compartido') {
                    $roomRows = $space->rooms
                        ->map(fn (SpaceRoom $room): array => [
                            'type' => 'shared_room',
                            'space_id' => $space->id,
                            'room_id' => $room->id,
                            'label' => $this->roomLabel($room),
                            'space_label' => $this->spaceLabel($space),
                            'cells' => $this->cells($dates, $availability, $blocks, $space->id, $room->id),
                        ])
                        ->values()
                        ->all();

                    return [
                        [
                            'type' => 'shared_space_group',
                            'space_id' => $space->id,
                            'room_id' => null,
                            'label' => $this->spaceLabel($space),
                            'cells' => [],
                        ],
                        ...$roomRows,
                    ];
                }

                return [[
                    'type' => 'private_space',
                    'space_id' => $space->id,
                    'room_id' => null,
                    'label' => $this->spaceLabel($space),
                    'cells' => $this->cells($dates, $availability, $blocks, $space->id, null),
                ]];
            })
            ->filter(fn (array $row): bool => $this->rowMatchesStatus($row, $filters['status'] ?? null))
            ->values()
            ->all();
    }

    private function cells(array $dates, Collection $availability, Collection $blocks, int $spaceId, ?int $roomId): array
    {
        return collect($dates)
            ->map(function (array $date) use ($availability, $blocks, $spaceId, $roomId): array {
                $day = $availability->get($this->resourceKey($spaceId, $roomId, $date['date']));
                $hasPrice = $day?->price !== null;
                $storedStatus = $hasPrice ? ($day?->status ?? 'closed') : 'closed';
                $isSoldOut = $this->isSoldOut($blocks, $spaceId, $roomId, $date['date']);
                $status = $isSoldOut ? 'sold_out' : $storedStatus;
                $meta = self::STATUS_META[$status];

                return [
                    'date' => $date['date'],
                    'status' => $status,
                    'stored_status' => $storedStatus,
                    'label' => $meta['label'],
                    'tone' => $meta['tone'],
                    'price' => $day?->price !== null ? number_format((float) $day->price, 2, '.', '') : null,
                    'has_price' => $hasPrice,
                    'availability_day_id' => $day?->id,
                    'is_sold_out' => $isSoldOut,
                ];
            })
            ->all();
    }

    private function isSoldOut(Collection $blocks, int $spaceId, ?int $roomId, string $date): bool
    {
        return $blocks->contains(fn (OccupancyBlock $block): bool => (int) $block->space_id === $spaceId
            && (int) ($block->space_room_id ?? 0) === (int) ($roomId ?? 0)
            && $block->start_date->toDateString() <= $date
            && $block->end_date->toDateString() >= $date);
    }

    private function rowMatchesStatus(array $row, ?string $status): bool
    {
        if (! filled($status) || $row['type'] === 'shared_space_group') {
            return true;
        }

        return collect($row['cells'])->contains(fn (array $cell): bool => $cell['status'] === $status);
    }

    private function summary(array $rows): array
    {
        $cells = collect($rows)
            ->reject(fn (array $row): bool => $row['type'] === 'shared_space_group')
            ->flatMap(fn (array $row): array => $row['cells']);

        return [
            'resources' => collect($rows)->where('type', '!=', 'shared_space_group')->count(),
            'available' => $cells->where('status', 'available')->count(),
            'closed' => $cells->where('status', 'closed')->count(),
            'sold_out' => $cells->where('status', 'sold_out')->count(),
            'without_price' => $cells->where('has_price', false)->count(),
        ];
    }

    private function validateResource(int $companyId, int|string $spaceId, int|string|null $roomId): array
    {
        $space = Space::query()
            ->with('spaceMode')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereKey($spaceId)
            ->first();

        if (! $space) {
            throw ValidationException::withMessages([
                'space_id' => 'El alojamiento debe pertenecer a tu empresa y estar activo.',
            ]);
        }

        $isShared = $space->spaceMode?->slug === 'compartido';

        if (! $isShared && filled($roomId)) {
            throw ValidationException::withMessages([
                'space_room_id' => 'Un alojamiento privado no permite seleccionar habitacion.',
            ]);
        }

        if (! $isShared) {
            return [$space, null];
        }

        if (! filled($roomId)) {
            throw ValidationException::withMessages([
                'space_room_id' => 'Selecciona una habitacion activa para el alojamiento compartido.',
            ]);
        }

        $room = SpaceRoom::query()
            ->where('company_id', $companyId)
            ->where('space_id', $space->id)
            ->where('status', 'active')
            ->whereKey($roomId)
            ->first();

        if (! $room) {
            throw ValidationException::withMessages([
                'space_room_id' => 'La habitacion seleccionada no pertenece al alojamiento.',
            ]);
        }

        return [$space, $room];
    }

    private function resourcesForBulk(int $companyId, array $data): Collection
    {
        if (filled($data['space_id'] ?? null) && filled($data['space_room_id'] ?? null)) {
            [$space, $room] = $this->validateResource($companyId, $data['space_id'], $data['space_room_id']);

            return collect([['space_id' => $space->id, 'room_id' => $room?->id]]);
        }

        $spaces = $this->spaces($companyId, $data);

        return $spaces
            ->flatMap(function (Space $space): array {
                if ($space->spaceMode?->slug === 'compartido') {
                    return $space->rooms
                        ->map(fn (SpaceRoom $room): array => ['space_id' => $space->id, 'room_id' => $room->id])
                        ->all();
                }

                return [['space_id' => $space->id, 'room_id' => null]];
            })
            ->values();
    }

    private function resourceKey(int $spaceId, ?int $roomId, string $date): string
    {
        return $spaceId.'|'.($roomId ?? 'space').'|'.$date;
    }

    private function spaceLabel(Space $space): string
    {
        $label = $space->spaceMode?->slug === 'compartido'
            ? ($space->name ?: $space->title ?: 'Alojamiento')
            : ($space->title ?: $space->name ?: 'Alojamiento');

        if ($space->spaceMode?->slug !== 'compartido' && (int) $space->max_capacity > 0) {
            return $label.' - Cap. '.$space->max_capacity;
        }

        return $label;
    }

    private function roomLabel(SpaceRoom $room): string
    {
        $label = $room->name ?: $room->title ?: 'Habitacion';
        $beds = $room->beds
            ->map(fn ($bed): string => trim($bed->quantity.' '.($bed->bedType?->name ?: 'cama')))
            ->filter()
            ->implode(', ');

        return filled($beds) ? $label.' - '.$beds : $label;
    }
}
