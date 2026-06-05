<?php

namespace App\Services\Occupancy;

use App\Models\AvailabilityDay;
use App\Models\OccupancyBlock;
use App\Models\Space;
use App\Models\SpaceRoom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class OccupancyGridService
{
    public const STATUS_META = [
        'available' => ['label' => 'Libre', 'tone' => 'available'],
        'manual_block' => ['label' => 'Bloqueado', 'tone' => 'manual-block'],
        'maintenance' => ['label' => 'Mant.', 'tone' => 'maintenance'],
        'owner_use' => ['label' => 'Prop.', 'tone' => 'owner-use'],
        'unavailable' => ['label' => 'No disp.', 'tone' => 'unavailable'],
    ];

    public function weekData(int $companyId, array $filters): array
    {
        $weekStart = $this->weekStart($filters['week_start'] ?? null);
        $weekEnd = $weekStart->copy()->addDays(6);
        $dates = $this->dates($weekStart);
        $spaces = $this->spaces($companyId, $filters);
        $blocks = $this->blocks($companyId, $weekStart, $weekEnd, $filters);
        $availabilityDays = $this->availabilityDays($companyId, $weekStart, $weekEnd, $filters);

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'previous_week' => $weekStart->copy()->subDay()->toDateString(),
            'current_week' => now()->subDay()->startOfDay()->toDateString(),
            'next_week' => $weekStart->copy()->addDay()->toDateString(),
            'dates' => $dates,
            'rows' => $this->rows($spaces, $dates, $blocks, $availabilityDays, $filters),
            'summary' => $this->summary($spaces, $blocks),
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

    private function weekStart(?string $date): Carbon
    {
        return filled($date)
            ? Carbon::parse($date)->startOfDay()
            : now()->subDay()->startOfDay();
    }

    private function dates(Carbon $weekStart): array
    {
        $dayNames = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'];

        return collect(range(0, 6))
            ->map(function (int $offset) use ($weekStart, $dayNames): array {
                $date = $weekStart->copy()->addDays($offset);

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

    private function blocks(int $companyId, Carbon $weekStart, Carbon $weekEnd, array $filters): Collection
    {
        return OccupancyBlock::query()
            ->with(['space.spaceMode', 'room'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('start_date', '<=', $weekEnd->toDateString())
            ->where('end_date', '>=', $weekStart->toDateString())
            ->when(filled($filters['space_id'] ?? null), fn (Builder $query): Builder => $query->where('space_id', $filters['space_id']))
            ->get();
    }

    private function availabilityDays(int $companyId, Carbon $weekStart, Carbon $weekEnd, array $filters): Collection
    {
        return AvailabilityDay::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->when(filled($filters['space_id'] ?? null), fn (Builder $query): Builder => $query->where('space_id', $filters['space_id']))
            ->get()
            ->keyBy(fn (AvailabilityDay $day): string => $this->availabilityKey((int) $day->space_id, $day->space_room_id ? (int) $day->space_room_id : null, $day->date->toDateString()));
    }

    private function rows(Collection $spaces, array $dates, Collection $blocks, Collection $availabilityDays, array $filters): array
    {
        return $spaces
            ->flatMap(function (Space $space) use ($dates, $blocks, $availabilityDays, $filters): array {
                if ($space->spaceMode?->slug === 'compartido') {
                    $roomRows = $space->rooms
                        ->map(fn ($room): array => [
                            'type' => 'shared_room',
                            'space_id' => $space->id,
                            'room_id' => $room->id,
                            'label' => $this->roomLabel($room),
                            'space_label' => $this->spaceLabel($space),
                            'cells' => $this->cells($dates, $blocks, $availabilityDays, $space->id, $room->id, $filters),
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
                    'cells' => $this->cells($dates, $blocks, $availabilityDays, $space->id, null, $filters),
                ]];
            })
            ->filter(fn (array $row): bool => $this->rowMatchesStatus($row, $filters['status'] ?? null))
            ->values()
            ->all();
    }

    private function cells(array $dates, Collection $blocks, Collection $availabilityDays, int $spaceId, ?int $roomId, array $filters): array
    {
        return collect($dates)
            ->map(function (array $date) use ($blocks, $availabilityDays, $spaceId, $roomId): array {
                $block = $this->blockForDate($blocks, $spaceId, $roomId, $date['date']);
                $availabilityDay = $availabilityDays->get($this->availabilityKey($spaceId, $roomId, $date['date']));
                $closedByAvailability = ! $block && (! $availabilityDay || $availabilityDay->price === null || $availabilityDay->status === 'closed');
                $status = $block?->type ?? ($closedByAvailability ? 'unavailable' : 'available');
                $meta = self::STATUS_META[$status] ?? self::STATUS_META['available'];

                return [
                    'date' => $date['date'],
                    'status' => $status,
                    'label' => $meta['label'],
                    'tone' => $meta['tone'],
                    'block_id' => $block?->id,
                    'title' => $block?->title,
                    'description' => $block?->description,
                    'start_date' => $block?->start_date?->toDateString(),
                    'end_date' => $block?->end_date?->toDateString(),
                    'closed_by_availability' => $closedByAvailability,
                    'actions' => $block
                        ? ['view', 'edit', 'cancel']
                        : ($closedByAvailability ? [] : ['create_block', 'maintenance', 'owner_use', 'unavailable']),
                ];
            })
            ->all();
    }

    private function blockForDate(Collection $blocks, int $spaceId, ?int $roomId, string $date): ?OccupancyBlock
    {
        return $blocks->first(fn (OccupancyBlock $block): bool => (int) $block->space_id === $spaceId
            && (int) ($block->space_room_id ?? 0) === (int) ($roomId ?? 0)
            && $block->start_date->toDateString() <= $date
            && $block->end_date->toDateString() >= $date);
    }

    private function availabilityKey(int $spaceId, ?int $roomId, string $date): string
    {
        return $spaceId.'|'.($roomId ?? 'space').'|'.$date;
    }

    private function rowMatchesStatus(array $row, ?string $status): bool
    {
        if (! filled($status) || $row['type'] === 'shared_space_group') {
            return true;
        }

        return collect($row['cells'])->contains(fn (array $cell): bool => $cell['status'] === $status);
    }

    private function summary(Collection $spaces, Collection $blocks): array
    {
        return [
            'private_spaces' => $spaces->where('spaceMode.slug', 'privado')->count(),
            'shared_rooms' => $spaces
                ->where('spaceMode.slug', 'compartido')
                ->sum(fn (Space $space): int => $space->rooms->count()),
            'blocks_this_week' => $blocks->count(),
        ];
    }

    private function spaceLabel(Space $space): string
    {
        $label = $space->spaceMode?->slug === 'compartido'
            ? ($space->name ?: $space->title ?: 'Alojamiento')
            : ($space->title ?: $space->name ?: 'Alojamiento');

        if ($space->spaceMode?->slug !== 'compartido' && (int) $space->max_capacity > 0) {
            return $label.' - '.$space->max_capacity;
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
