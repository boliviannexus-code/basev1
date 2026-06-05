<?php

namespace App\Services\PublicSite;

use App\Models\AvailabilityDay;
use App\Models\OccupancyBlock;
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

    public function detail(Space $space, array $filters): ?array
    {
        $space->load($this->relations());

        if ($space->status !== 'active') {
            return null;
        }

        $result = $this->makeResult($space, $filters);

        if ($this->hasDateRange($filters) && ! $result['is_available']) {
            return null;
        }

        return $result;
    }

    private function baseQuery(array $filters): Builder
    {
        $query = Space::query()
            ->withoutGlobalScope('company')
            ->with($this->relations())
            ->where('status', 'active')
            ->whereHas('company', fn (Builder $company): Builder => $company->where('is_active', true))
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

    private function makeResult(Space $space, array $filters): array
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

        if ($dateRange !== []) {
            $isAvailable = $isAvailable
                && $this->hasAvailableDays($space, null, $dateRange)
                && ! $this->hasActiveBlock($space, null, $filters);
        }

        return [
            'capacity' => $capacity,
            'rooms_available' => null,
            'price_from' => $priceFrom,
            'is_available' => $isAvailable,
            'note' => $dateRange === [] ? 'Consulta fechas para confirmar disponibilidad.' : ($isAvailable ? 'Disponible para las fechas consultadas.' : 'No disponible para las fechas consultadas.'),
        ];
    }

    private function sharedAvailability(Space $space, array $filters): array
    {
        $guests = (int) ($filters['guests'] ?? 1);
        $dateRange = $this->nightDates($filters);
        $rooms = $space->rooms
            ->where('status', 'active')
            ->filter(function (SpaceRoom $room) use ($space, $filters, $dateRange): bool {
                if ($dateRange === []) {
                    return true;
                }

                return $this->hasAvailableDays($space, $room, $dateRange)
                    && ! $this->hasActiveBlock($space, $room, $filters);
            })
            ->values();
        $capacity = $rooms->sum(fn (SpaceRoom $room): int => $this->roomCapacity($room));
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

    private function hasAvailableDays(Space $space, ?SpaceRoom $room, array $dateRange): bool
    {
        if ($dateRange === []) {
            return false;
        }

        $count = AvailabilityDay::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->when($room, fn (Builder $query): Builder => $query->where('space_room_id', $room->id), fn (Builder $query): Builder => $query->whereNull('space_room_id'))
            ->whereIn('date', $dateRange)
            ->where('status', 'available')
            ->whereNotNull('price')
            ->count();

        return $count === count($dateRange);
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
        $query = AvailabilityDay::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->whereNull('space_room_id')
            ->where('status', 'available')
            ->whereNotNull('price');

        if ($dateRange !== []) {
            $query->whereIn('date', $dateRange);
        }

        $price = $query->min('price');

        return $price === null ? null : (float) $price;
    }

    private function sharedPriceFrom(Space $space, Collection $rooms, array $dateRange): ?float
    {
        if ($rooms->isEmpty()) {
            return null;
        }

        $query = AvailabilityDay::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->whereIn('space_room_id', $rooms->pluck('id'))
            ->where('status', 'available')
            ->whereNotNull('price');

        if ($dateRange !== []) {
            $query->whereIn('date', $dateRange);
        }

        $price = $query->min('price');

        return $price === null ? null : (float) $price;
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
            'rooms' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order')->orderBy('id'),
            'rooms.beds.bedType',
            'rooms.photos' => fn ($query) => $query->orderBy('sort_order'),
            'rooms.roomServices' => fn ($query) => $query->orderBy('name'),
        ];
    }
}
