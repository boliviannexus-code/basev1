<?php

namespace App\Services\PublicSite;

use App\Models\AvailabilityDay;
use App\Models\AvailabilityStatus;
use App\Models\OccupancyBlock;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class PublicAccommodationSearchService
{
    private const int DESTINATION_RADIUS_KM = 25;

    public function search(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $results = $this->baseQuery($filters)
            ->get()
            ->map(fn (Space $space): array => $this->makeResult($space, $filters))
            ->filter(fn (array $result): bool => $result['is_available'] && $this->matchesDestination($result, $filters))
            ->pipe(fn (Collection $results): Collection => $this->sortResults($results, $filters))
            ->values();
        $page = Paginator::resolveCurrentPage();

        return new Paginator(
            $results->forPage($page, $perPage)->values(),
            $results->count(),
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
    }

    public function featured(array $filters = [], int $limit = 6): Collection
    {
        return $this->baseQuery($filters)
            ->get()
            ->map(fn (Space $space): array => $this->makeResult($space, $filters))
            ->filter(fn (array $result): bool => $result['is_available'] && $this->matchesDestination($result, $filters))
            ->pipe(fn (Collection $results): Collection => $this->sortResults($results, $filters))
            ->take($limit)
            ->values();
    }

    public function detail(Space $space, array $filters, bool $includePublicCalendar = true): ?array
    {
        $space->load($this->relations());

        if ($space->status !== 'active' || ! $space->is_public_online || ! $space->company?->is_active) {
            return null;
        }

        $result = $this->makeResult($space, $filters, includePublicCalendar: $includePublicCalendar);

        return $result;
    }

    private function baseQuery(array $filters): Builder
    {
        $query = Space::query()
            ->withoutGlobalScope('company')
            ->with($this->relations())
            ->publicBookable()
            ->whereHas('spaceMode', fn (Builder $mode): Builder => $mode->whereIn('slug', ['privado', 'compartido']));

        if (($destination = $filters['destination'] ?? null) && ! $this->hasGoogleDestination($filters)) {
            $query->where(function (Builder $scope) use ($destination): void {
                $scope
                    ->where('title', 'like', "%{$destination}%")
                    ->orWhere('name', 'like', "%{$destination}%")
                    ->orWhere('short_description', 'like', "%{$destination}%")
                    ->orWhere('full_description', 'like', "%{$destination}%")
                    ->orWhereHas('location', function (Builder $location) use ($destination): void {
                        $location
                            ->where('city', 'like', "%{$destination}%")
                            ->orWhere('zone_or_neighborhood', 'like', "%{$destination}%")
                            ->orWhere('address', 'like', "%{$destination}%")
                            ->orWhere('state_or_region', 'like', "%{$destination}%")
                            ->orWhere('country', 'like', "%{$destination}%");
                    });
            });
        }

        return $query->orderByRaw('COALESCE(title, name) ASC');
    }

    private function makeResult(Space $space, array $filters, bool $includePublicCalendar = false): array
    {
        $mode = $space->spaceMode?->slug === 'compartido' ? 'shared' : 'private';
        $availability = $mode === 'shared'
            ? $this->sharedAvailability($space, $filters)
            : $this->privateAvailability($space, $filters);
        $image = $space->photos->sortBy('sort_order')->first();

        return [
            'space' => $space,
            'mode' => $mode,
            'label' => $this->typeLabel($space, $mode),
            'title' => $space->title ?: $space->name,
            'location' => $this->locationText($space),
            'capacity' => $availability['capacity'],
            'rooms_available' => $availability['rooms_available'],
            'price_from' => $availability['price_from'],
            'is_available' => $availability['is_available'],
            'availability_note' => $availability['note'],
            'availability_calendar' => $availability['calendar'] ?? collect(),
            'public_calendar' => $includePublicCalendar ? $this->publicAvailabilityCalendar($space, $mode) : collect(),
            'image_url' => $image ? Storage::disk('public')->url($image->path) : null,
            'search_distance_km' => $this->destinationDistance($space, $filters),
            'query' => $this->queryPayload($filters),
            'rooms' => $availability['rooms'] ?? collect(),
        ];
    }

    private function privateAvailability(Space $space, array $filters): array
    {
        $capacity = (int) $space->max_capacity;
        $guests = (int) ($filters['guests'] ?? 1);
        $dateRange = $this->nightDates($filters);
        $priceFrom = $this->privatePriceFrom($space, $dateRange);
        $isAvailable = $capacity >= $guests;
        $calendar = $this->dateAvailability($space, null, $filters, $dateRange);

        if ($dateRange !== []) {
            $isAvailable = $isAvailable
                && $calendar->every(fn (array $day): bool => $day['status'] === 'available');
        }

        return [
            'capacity' => $capacity,
            'rooms_available' => null,
            'price_from' => $priceFrom,
            'is_available' => $isAvailable,
            'note' => $dateRange === [] ? 'Consulta fechas para confirmar disponibilidad.' : ($isAvailable ? 'Disponible para las fechas consultadas.' : 'No disponible para las fechas consultadas.'),
            'calendar' => $calendar,
        ];
    }

    private function sharedAvailability(Space $space, array $filters): array
    {
        $guests = (int) ($filters['guests'] ?? 1);
        $dateRange = $this->nightDates($filters);
        $rooms = $space->rooms
            ->where('status', 'active')
            ->filter(function (SpaceRoom $room) use ($space, $filters, $dateRange): bool {
                if ($this->roomSellsBeds($room)) {
                    $availableBedUnits = $this->availableBedUnits($space, $room, $filters, $dateRange);
                    $room->setRelation('availableBedUnits', $availableBedUnits);

                    return $dateRange === []
                        ? $availableBedUnits->isNotEmpty()
                        : $this->hasAvailableDays($space, $room, $dateRange)
                            && ! $this->hasActiveBlock($space, $room, $filters)
                            && $availableBedUnits->isNotEmpty();
                }

                if ($dateRange === []) {
                    return true;
                }

                return $this->hasAvailableDays($space, $room, $dateRange)
                    && ! $this->hasActiveBlock($space, $room, $filters);
            })
            ->values();
        $capacity = $rooms->sum(function (SpaceRoom $room): int {
            if ($this->roomSellsBeds($room)) {
                $availableBedUnits = $room->relationLoaded('availableBedUnits')
                    ? $room->getRelation('availableBedUnits')
                    : $room->bedUnits->where('status', 'active')->values();

                return (int) $availableBedUnits->sum(fn (RoomBedUnit $unit): int => (int) ($unit->bedType?->capacity ?: 1));
            }

            return $this->roomCapacity($room);
        });
        $priceFrom = $this->sharedPriceFrom($space, $rooms, $dateRange);
        $isAvailable = $rooms->isNotEmpty() && $capacity >= $guests;

        return [
            'capacity' => $capacity,
            'rooms_available' => $rooms->count(),
            'price_from' => $priceFrom,
            'is_available' => $isAvailable,
            'note' => $dateRange === [] ? 'Habitaciones sujetas a disponibilidad por fecha.' : ($isAvailable ? 'Habitaciones disponibles para tu busqueda.' : 'Sin habitaciones suficientes para la busqueda.'),
            'rooms' => $rooms,
        ];
    }

    private function publicAvailabilityCalendar(Space $space, string $mode, int $days = 90): Collection
    {
        if ($mode !== 'private') {
            return collect();
        }

        $start = CarbonImmutable::today();
        $end = $start->addDays($days - 1);
        $dateRange = collect(CarbonPeriod::create($start, $end))
            ->map(fn ($date): string => $date->toDateString())
            ->all();

        return $this->dateAvailability($space, null, [
            'check_in' => $start->toDateString(),
            'check_out' => $end->addDay()->toDateString(),
        ], $dateRange);
    }

    private function hasAvailableDays(Space $space, ?SpaceRoom $room, array $dateRange): bool
    {
        if ($dateRange === []) {
            return false;
        }

        $availableDays = $this->availableDays($space, $room, $dateRange);

        if ($availableDays->count() !== count($dateRange)) {
            return false;
        }

        return ! AvailabilityStatus::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->when($room, fn (Builder $query): Builder => $query->where('space_room_id', $room->id), fn (Builder $query): Builder => $query->whereNull('space_room_id'))
            ->whereNull('room_bed_unit_id')
            ->whereIn('date', $dateRange)
            ->whereIn('status', ['closed', 'reserved', 'occupied'])
            ->exists();
    }

    private function dateAvailability(Space $space, ?SpaceRoom $room, array $filters, array $dateRange): Collection
    {
        if ($dateRange === []) {
            return collect();
        }

        $availableDays = $this->availableDays($space, $room, $dateRange);
        $statuses = AvailabilityStatus::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->when($room, fn (Builder $query): Builder => $query->where('space_room_id', $room->id), fn (Builder $query): Builder => $query->whereNull('space_room_id'))
            ->whereNull('room_bed_unit_id')
            ->whereIn('date', $dateRange)
            ->whereIn('status', ['closed', 'reserved', 'occupied'])
            ->get()
            ->keyBy(fn (AvailabilityStatus $status): string => $status->date->toDateString());
        $blocks = $this->activeBlocks($space, $room, $filters);

        return collect($dateRange)
            ->map(function (string $date) use ($availableDays, $statuses, $blocks): array {
                $status = $statuses->get($date);
                $block = $blocks->first(fn (OccupancyBlock $block): bool => $block->start_date->toDateString() <= $date && $block->end_date->toDateString() >= $date);

                if ($block || in_array($status?->status, ['reserved', 'occupied'], true)) {
                    return $this->dateAvailabilityPayload($date, 'occupied', 'Ocupada');
                }

                if ($status?->status === 'closed' || ! $availableDays->has($date)) {
                    return $this->dateAvailabilityPayload($date, 'unavailable', 'No disponible');
                }

                return $this->dateAvailabilityPayload($date, 'available', 'Disponible');
            })
            ->values();
    }

    private function availableDays(Space $space, ?SpaceRoom $room, array $dateRange): Collection
    {
        return AvailabilityDay::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->when(
                $room,
                fn (Builder $query): Builder => $query->where('space_room_id', $room->id)->whereNull('room_bed_unit_id'),
                fn (Builder $query): Builder => $query->whereNull('space_room_id')->whereNull('room_bed_unit_id'),
            )
            ->whereIn('date', $dateRange)
            ->where('status', 'available')
            ->where('is_public_online', true)
            ->whereNotNull('price')
            ->get()
            ->keyBy(fn (AvailabilityDay $day): string => $day->date->toDateString());
    }

    private function activeBlocks(Space $space, ?SpaceRoom $room, array $filters): Collection
    {
        if (! $this->hasDateRange($filters)) {
            return collect();
        }

        $checkIn = CarbonImmutable::parse($filters['check_in'])->toDateString();
        $lastNight = CarbonImmutable::parse($filters['check_out'])->subDay()->toDateString();

        return OccupancyBlock::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->when($room, fn (Builder $query): Builder => $query->where('space_room_id', $room->id), fn (Builder $query): Builder => $query->whereNull('space_room_id'))
            ->whereNull('room_bed_unit_id')
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('reservation', fn (Builder $reservation): Builder => $this->expiredPendingHold($reservation))
                    ->whereDoesntHave('reservationRoom.reservation', fn (Builder $reservation): Builder => $this->expiredPendingHold($reservation));
            })
            ->whereDate('start_date', '<=', $lastNight)
            ->whereDate('end_date', '>=', $checkIn)
            ->get();
    }

    private function dateAvailabilityPayload(string $date, string $status, string $label): array
    {
        return [
            'date' => $date,
            'display_date' => CarbonImmutable::parse($date)->format('d/m/Y'),
            'status' => $status,
            'label' => $label,
        ];
    }

    private function hasActiveBlock(Space $space, ?SpaceRoom $room, array $filters): bool
    {
        if (! $this->hasDateRange($filters)) {
            return false;
        }

        $checkIn = CarbonImmutable::parse($filters['check_in'])->toDateString();
        $lastNight = CarbonImmutable::parse($filters['check_out'])->subDay()->toDateString();

        return OccupancyBlock::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->when($room, fn (Builder $query): Builder => $query->where('space_room_id', $room->id), fn (Builder $query): Builder => $query->whereNull('space_room_id'))
            ->whereNull('room_bed_unit_id')
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('reservation', fn (Builder $reservation): Builder => $this->expiredPendingHold($reservation))
                    ->whereDoesntHave('reservationRoom.reservation', fn (Builder $reservation): Builder => $this->expiredPendingHold($reservation));
            })
            ->whereDate('start_date', '<=', $lastNight)
            ->whereDate('end_date', '>=', $checkIn)
            ->exists();
    }

    private function availableBedUnits(Space $space, SpaceRoom $room, array $filters, array $dateRange): Collection
    {
        $units = $room->bedUnits
            ->where('status', 'active')
            ->sortBy('sort_order')
            ->values();

        if ($dateRange === []) {
            return $units;
        }

        return $units
            ->filter(fn (RoomBedUnit $unit): bool => $this->availableDaysForBedUnit($space, $unit, $dateRange)->count() === count($dateRange)
                && ! $this->hasUnavailableStatusForBedUnit($space, $unit, $dateRange)
                && ! $this->hasActiveBlockForBedUnit($space, $unit, $filters))
            ->values();
    }

    private function hasUnavailableStatusForBedUnit(Space $space, RoomBedUnit $unit, array $dateRange): bool
    {
        return AvailabilityStatus::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->where('space_room_id', $unit->space_room_id)
            ->where('room_bed_unit_id', $unit->id)
            ->whereIn('date', $dateRange)
            ->whereIn('status', ['closed', 'reserved', 'occupied'])
            ->exists();
    }

    private function hasActiveBlockForBedUnit(Space $space, RoomBedUnit $unit, array $filters): bool
    {
        if (! $this->hasDateRange($filters)) {
            return false;
        }

        $checkIn = CarbonImmutable::parse($filters['check_in'])->toDateString();
        $lastNight = CarbonImmutable::parse($filters['check_out'])->subDay()->toDateString();

        return OccupancyBlock::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->where('room_bed_unit_id', $unit->id)
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('reservation', fn (Builder $reservation): Builder => $this->expiredPendingHold($reservation))
                    ->whereDoesntHave('reservationBedUnit.reservation', fn (Builder $reservation): Builder => $this->expiredPendingHold($reservation));
            })
            ->whereDate('start_date', '<=', $lastNight)
            ->whereDate('end_date', '>=', $checkIn)
            ->exists();
    }

    private function expiredPendingHold(Builder $query): Builder
    {
        return $query
            ->withoutGlobalScope('company')
            ->where('status', 'pending_payment')
            ->where('payment_status', 'pending')
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now());
    }

    private function privatePriceFrom(Space $space, array $dateRange): ?float
    {
        $price = $space->accommodationPackages
            ->where('is_active', true)
            ->min('price');

        return $price === null ? null : (float) $price;
    }

    private function sharedPriceFrom(Space $space, Collection $rooms, array $dateRange): ?float
    {
        if ($rooms->isEmpty()) {
            return null;
        }

        if ($dateRange === []) {
            return null;
        }

        $prices = $rooms
            ->flatMap(function (SpaceRoom $room) use ($space, $dateRange): Collection {
                $prices = collect();

                if (in_array($room->sale_mode, ['full_room', 'flexible'], true)) {
                    $roomDays = $this->availableDays($space, $room, $dateRange);

                    if ($roomDays->count() === count($dateRange)) {
                        $prices->push(round((float) $roomDays->sum(fn (AvailabilityDay $day): float => (float) $day->price) / count($dateRange), 2));
                    }
                }

                if ($this->roomSellsBeds($room)) {
                    $room->bedUnits
                        ->where('status', 'active')
                        ->each(function (RoomBedUnit $unit) use ($space, $dateRange, $prices): void {
                            $bedUnitDays = $this->availableDaysForBedUnit($space, $unit, $dateRange);

                            if ($bedUnitDays->count() === count($dateRange)) {
                                $prices->push(round((float) $bedUnitDays->sum(fn (AvailabilityDay $day): float => (float) $day->price) / count($dateRange), 2));
                            }
                        });
                }

                return $prices;
            })
            ->filter(fn (float $price): bool => $price > 0);

        $price = $prices->min();

        return $price === null ? null : (float) $price;
    }

    private function availableDaysForBedUnit(Space $space, RoomBedUnit $unit, array $dateRange): Collection
    {
        return AvailabilityDay::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->where('space_room_id', $unit->space_room_id)
            ->where(function (Builder $query) use ($unit): void {
                $query
                    ->where('room_bed_unit_id', $unit->id)
                    ->orWhereNull('room_bed_unit_id');
            })
            ->whereIn('date', $dateRange)
            ->where('status', 'available')
            ->where('is_public_online', true)
            ->whereNotNull('price')
            ->get()
            ->groupBy(fn (AvailabilityDay $day): string => $day->date->toDateString())
            ->map(fn (Collection $days): AvailabilityDay => $days
                ->sortByDesc(fn (AvailabilityDay $day): int => $day->room_bed_unit_id === null ? 0 : 1)
                ->first())
            ->filter()
            ->sortKeys();
    }

    private function nightDates(array $filters): array
    {
        if (! $this->hasDateRange($filters)) {
            return [];
        }

        $start = CarbonImmutable::parse($filters['check_in']);
        $end = CarbonImmutable::parse($filters['check_out'])->subDay();

        return collect(CarbonPeriod::create($start, $end))
            ->map(fn ($date): string => $date->toDateString())
            ->all();
    }

    private function hasDateRange(array $filters): bool
    {
        return filled($filters['check_in'] ?? null) && filled($filters['check_out'] ?? null);
    }

    private function roomCapacity(SpaceRoom $room): int
    {
        $bedsCapacity = (int) $room->beds->sum('total_capacity');

        return (int) ($room->max_capacity ?: $bedsCapacity);
    }

    private function roomSellsBeds(SpaceRoom $room): bool
    {
        return in_array($room->sale_mode, ['bed_unit', 'flexible'], true)
            && $room->relationLoaded('bedUnits')
            && $room->bedUnits->where('status', 'active')->isNotEmpty();
    }

    private function typeLabel(Space $space, string $mode): string
    {
        if ($mode === 'shared') {
            return $space->sharedSpaceType?->name ?: 'Tipo por definir';
        }

        return $space->privateSpaceType?->name ?: 'Tipo por definir';
    }

    private function locationText(Space $space): string
    {
        $location = $space->location;
        $parts = collect([
            $location?->city,
            $location?->zone_or_neighborhood,
            $location?->state_or_region,
        ])->filter()->values();

        return $parts->isEmpty() ? 'Ubicacion por confirmar' : $parts->implode(', ');
    }

    private function queryPayload(array $filters): array
    {
        return collect($filters)
            ->only([
                'destination',
                'destination_latitude',
                'destination_longitude',
                'destination_city',
                'destination_state',
                'destination_country',
                'check_in',
                'check_out',
                'guests',
                'package_id',
            ])
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->all();
    }

    private function matchesDestination(array $result, array $filters): bool
    {
        if ($this->hasDestinationCoordinates($filters)) {
            return $result['search_distance_km'] !== null
                ? $result['search_distance_km'] <= self::DESTINATION_RADIUS_KM
                : $this->matchesDestinationComponents($result['space'], $filters);
        }

        if ($this->hasDestinationComponents($filters)) {
            return $this->matchesDestinationComponents($result['space'], $filters);
        }

        return true;
    }

    private function sortResults(Collection $results, array $filters): Collection
    {
        if (! $this->hasDestinationCoordinates($filters)) {
            return $results->sortBy(fn (array $result): string => mb_strtolower((string) $result['title']));
        }

        return $results->sortBy([
            fn (array $result): float => $result['search_distance_km'] ?? PHP_FLOAT_MAX,
            fn (array $result): string => mb_strtolower((string) $result['title']),
        ]);
    }

    private function destinationDistance(Space $space, array $filters): ?float
    {
        if (! $this->hasDestinationCoordinates($filters)) {
            return null;
        }

        $location = $space->location;

        if (! filled($location?->latitude) || ! filled($location?->longitude)) {
            return null;
        }

        return $this->haversineDistanceKm(
            (float) $filters['destination_latitude'],
            (float) $filters['destination_longitude'],
            (float) $location->latitude,
            (float) $location->longitude,
        );
    }

    private function haversineDistanceKm(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadiusKm = 6371;
        $latDelta = deg2rad($toLat - $fromLat);
        $lngDelta = deg2rad($toLng - $fromLng);
        $fromLat = deg2rad($fromLat);
        $toLat = deg2rad($toLat);
        $angle = sin($latDelta / 2) ** 2
            + cos($fromLat) * cos($toLat) * sin($lngDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($angle), sqrt(1 - $angle));
    }

    private function matchesDestinationComponents(Space $space, array $filters): bool
    {
        $location = $space->location;

        if (! $location) {
            return false;
        }

        $city = $filters['destination_city'] ?? null;
        $state = $filters['destination_state'] ?? null;
        $country = $filters['destination_country'] ?? null;

        if (filled($city) && $this->sameLocationText($location->city, $city)) {
            return true;
        }

        if (filled($state) && $this->sameLocationText($location->state_or_region, $state)) {
            return true;
        }

        return filled($country) && ! filled($city) && ! filled($state)
            && $this->sameLocationText($location->country, $country);
    }

    private function sameLocationText(?string $left, ?string $right): bool
    {
        return filled($left)
            && filled($right)
            && $this->normalizeLocationText($left) === $this->normalizeLocationText($right);
    }

    private function normalizeLocationText(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/\b(departamento|department|provincia|province|municipio|municipality)\s+(de\s+)?/', '')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();
    }

    private function hasGoogleDestination(array $filters): bool
    {
        return $this->hasDestinationCoordinates($filters) || $this->hasDestinationComponents($filters);
    }

    private function hasDestinationCoordinates(array $filters): bool
    {
        return filled($filters['destination_latitude'] ?? null) && filled($filters['destination_longitude'] ?? null);
    }

    private function hasDestinationComponents(array $filters): bool
    {
        return filled($filters['destination_city'] ?? null)
            || filled($filters['destination_state'] ?? null)
            || filled($filters['destination_country'] ?? null);
    }

    private function relations(): array
    {
        return [
            'company',
            'spaceMode',
            'privateSpaceType',
            'sharedSpaceType',
            'location',
            'photos' => fn ($query) => $query->orderBy('sort_order'),
            'generalServices' => fn ($query) => $query->orderBy('name'),
            'accommodationPackages' => fn ($query) => $query
                ->where('is_active', true)
                ->with(['services' => fn ($serviceQuery) => $serviceQuery
                    ->where('package_services.is_active', true)
                    ->orderByPivot('sort_order')
                    ->orderBy('package_services.name')])
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->orderBy('name'),
            'rooms' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order')->orderBy('id'),
            'rooms.beds.bedType',
            'rooms.bedUnits.bedType',
            'rooms.photos' => fn ($query) => $query->orderBy('sort_order'),
            'rooms.roomServices' => fn ($query) => $query->orderBy('name'),
        ];
    }
}
