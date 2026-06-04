<?php

namespace App\Services;

use App\Models\Tour;
use App\Models\TourAvailability;
use App\Models\TourPrice;
use App\Repositories\TourAvailabilityRepository;
use App\Support\CompanyContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TourAvailabilityService
{
    public function __construct(
        private readonly TourAvailabilityRepository $availability,
    ) {}

    public function toursForSelect(): Collection
    {
        return $this->availability->queryAccessibleTours()
            ->with('category')
            ->orderBy('title')
            ->get(['id', 'company_id', 'category_id', 'title', 'name']);
    }

    public function grid(array $filters): array
    {
        $start = CarbonImmutable::parse($filters['start_date'] ?? now()->toDateString())->startOfDay();
        $days = max(7, min((int) ($filters['days'] ?? 90), 180));
        $end = $start->addDays($days - 1);
        $tourId = isset($filters['tour_id']) && $filters['tour_id'] !== '' ? (int) $filters['tour_id'] : null;
        $dates = $this->dates($start, $end);

        $tours = $this->availability->queryAccessibleTours()
            ->with(['category', 'prices', 'availabilities' => function ($query) use ($start, $end): void {
                $query->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->with('prices');
            }])
            ->when($tourId, fn ($query) => $query->whereKey($tourId))
            ->orderBy('title')
            ->get();

        return [
            'dates' => $dates->map(fn (CarbonImmutable $date): array => $this->datePayload($date))->all(),
            'tours' => $tours->map(fn (Tour $tour): array => $this->tourPayload($tour, $dates))->all(),
            'statuses' => TourAvailability::STATUSES,
            'range' => [
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $days,
            ],
        ];
    }

    public function saveDay(array $data): TourAvailability
    {
        $tour = $this->findAccessibleTour((int) $data['tour_id']);

        if ($tour->status !== Tour::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'tour_id' => 'Solo se puede modificar la disponibilidad de tours activos.',
            ]);
        }

        $date = CarbonImmutable::parse($data['date'])->toDateString();
        $availability = $this->availability->firstOrCreate($tour, $date);

        $payload = [
            'status' => $data['status'],
            'capacity' => $this->normalizeCapacity($tour, $data['capacity'] ?? null),
        ];

        if (array_key_exists('restrictions', $data)) {
            $payload['restrictions'] = $data['restrictions'] ?? null;
        }

        $availability->update($payload);

        $this->syncPrices($availability, $tour, $data['prices'] ?? null);

        return $availability->refresh()->load('prices');
    }

    public function bulkUpdate(array $data): int
    {
        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);

        if ($end->lt($start) || $start->diffInDays($end) > 180) {
            throw ValidationException::withMessages([
                'end_date' => 'El rango de fechas no es valido.',
            ]);
        }

        $tourIds = isset($data['tour_id']) && $data['tour_id'] !== '' ? [(int) $data['tour_id']] : null;
        $tours = $this->availability->queryAccessibleTours()
            ->with('prices')
            ->where('status', Tour::STATUS_ACTIVE)
            ->when($tourIds, fn ($query) => $query->whereIn('id', $tourIds))
            ->get();
        $weekdays = collect($data['weekdays'] ?? [0, 1, 2, 3, 4, 5, 6])->map(fn ($day): int => (int) $day)->all();
        $updated = 0;

        foreach ($tours as $tour) {
            foreach (CarbonPeriod::create($start, $end) as $date) {
                $date = CarbonImmutable::instance($date);

                if (! in_array($date->dayOfWeek, $weekdays, true)) {
                    continue;
                }

                $availability = $this->availability->firstOrCreate($tour, $date->toDateString());
                $payload = [];

                foreach (['status', 'capacity', 'restrictions'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $payload[$field] = $field === 'capacity'
                            ? $this->normalizeCapacity($tour, $data[$field])
                            : $data[$field];
                    }
                }

                if ($payload !== []) {
                    $availability->update($payload);
                }

                if (array_key_exists('bulk_price_usd', $data) && $data['bulk_price_usd'] !== null && $data['bulk_price_usd'] !== '') {
                    $this->syncPrices($availability, $tour, $tour->prices->map(fn (TourPrice $price): array => [
                        'tour_price_id' => $price->id,
                        'title' => $price->title,
                        'min_people' => $price->min_people,
                        'max_people' => $price->max_people,
                        'price_usd' => $data['bulk_price_usd'],
                    ])->all());
                } elseif (array_key_exists('prices', $data)) {
                    $this->syncPrices($availability, $tour, $data['prices'] ?? []);
                }

                $updated++;
            }
        }

        return $updated;
    }

    private function dates(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return collect(iterator_to_array(CarbonPeriod::create($start, $end)))
            ->map(fn ($date): CarbonImmutable => CarbonImmutable::instance($date));
    }

    private function datePayload(CarbonImmutable $date): array
    {
        return [
            'date' => $date->toDateString(),
            'month' => $date->translatedFormat('M Y'),
            'weekday' => $date->translatedFormat('D'),
            'day' => $date->format('d'),
            'is_week_start' => $date->dayOfWeek === 1,
            'is_weekend' => in_array($date->dayOfWeek, [0, 6], true),
        ];
    }

    private function tourPayload(Tour $tour, Collection $dates): array
    {
        $availabilities = $tour->availabilities->keyBy(fn (TourAvailability $availability): string => $availability->date->toDateString());

        return [
            'id' => $tour->id,
            'title' => $tour->display_title ?: 'Tour sin titulo',
            'is_active' => $tour->status === Tour::STATUS_ACTIVE,
            'prices' => $tour->prices->map(fn (TourPrice $price): array => $this->pricePayload($price))->all(),
            'days' => $dates->map(function (CarbonImmutable $date) use ($tour, $availabilities): array {
                $availability = $availabilities->get($date->toDateString());

                return $this->availabilityPayload($tour, $date, $availability);
            })->keyBy('date')->all(),
        ];
    }

    private function availabilityPayload(Tour $tour, CarbonImmutable $date, ?TourAvailability $availability): array
    {
        $prices = $availability
            ? $availability->prices->map(fn ($price): array => [
                'tour_price_id' => $price->tour_price_id,
                'title' => $price->title,
                'min_people' => $price->min_people,
                'max_people' => $price->max_people,
                'price_usd' => (float) $price->price_usd,
            ])->values()
            : $tour->prices->map(fn (TourPrice $price): array => $this->pricePayload($price));

        return [
            'date' => $date->toDateString(),
            'status' => $availability?->status ?? TourAvailability::STATUS_CLOSED,
            'status_label' => $availability?->status_label ?? TourAvailability::STATUSES[TourAvailability::STATUS_CLOSED],
            'capacity' => $this->effectiveCapacity($tour, $availability),
            'uses_default_capacity' => $availability === null || $availability->capacity === null,
            'booked_count' => $availability?->booked_count ?? 0,
            'restrictions' => $availability?->restrictions,
            'prices' => $prices->all(),
        ];
    }

    private function effectiveCapacity(Tour $tour, ?TourAvailability $availability): ?int
    {
        return $availability?->capacity ?? $tour->capacity;
    }

    private function normalizeCapacity(Tour $tour, mixed $capacity): ?int
    {
        $capacity = $capacity === '' ? null : $capacity;
        $capacity = $capacity === null ? $tour->capacity : (int) $capacity;

        if ($tour->capacity !== null && $capacity !== null && $capacity > $tour->capacity) {
            throw ValidationException::withMessages([
                'capacity' => 'El cupo del dia no puede superar el cupo maximo registrado para el tour.',
            ]);
        }

        return $capacity;
    }

    private function pricePayload(TourPrice $price): array
    {
        return [
            'tour_price_id' => $price->id,
            'title' => $price->title,
            'min_people' => $price->min_people,
            'max_people' => $price->max_people,
            'price_usd' => (float) $price->price_usd,
        ];
    }

    private function findAccessibleTour(int $tourId): Tour
    {
        $tour = $this->availability->queryAccessibleTours()
            ->with('prices')
            ->findOrFail($tourId);

        if (! CompanyContext::belongsToUser((int) $tour->company_id, auth()->user())) {
            abort(403);
        }

        return $tour;
    }

    private function syncPrices(TourAvailability $availability, Tour $tour, ?array $prices): void
    {
        $prices = $prices ?? $tour->prices->map(fn (TourPrice $price): array => $this->pricePayload($price))->all();
        $tourPriceIds = $tour->prices->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach ($prices as $price) {
            $tourPriceId = isset($price['tour_price_id']) ? (int) $price['tour_price_id'] : null;

            if ($tourPriceId !== null && ! in_array($tourPriceId, $tourPriceIds, true)) {
                continue;
            }

            $availability->prices()->updateOrCreate(
                ['tour_price_id' => $tourPriceId],
                [
                    'title' => filled($price['title'] ?? null) ? trim((string) $price['title']) : null,
                    'min_people' => (int) $price['min_people'],
                    'max_people' => isset($price['max_people']) && $price['max_people'] !== '' ? (int) $price['max_people'] : null,
                    'price_usd' => number_format((float) $price['price_usd'], 2, '.', ''),
                ],
            );
        }
    }
}
