<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Tour;
use App\Models\TourAvailability;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicTourService
{
    public function featuredTours(int $limit = 6): Collection
    {
        return $this->baseQuery()
            ->whereHas('prices')
            ->latest()
            ->limit($limit)
            ->get();
    }

    public function categories(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->withCount(['tours' => fn (Builder $query) => $query->publiclyBookable()])
            ->orderBy('name')
            ->get(['id', 'name', 'description']);
    }

    public function search(array $filters, int $perPage = 9): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->when($filters['destination'] ?? null, function (Builder $query, string $destination): void {
                $destination = $this->normalizeDestination($destination);

                $query->where(function (Builder $query) use ($destination): void {
                    $query
                        ->whereRaw('LOWER(city) LIKE ?', ["%{$destination}%"])
                        ->orWhereRaw('LOWER(country) LIKE ?', ["%{$destination}%"])
                        ->orWhereRaw('LOWER(location_text) LIKE ?', ["%{$destination}%"])
                        ->orWhereRaw('LOWER(title) LIKE ?', ["%{$destination}%"]);
                });
            })
            ->when($filters['category'] ?? null, fn (Builder $query, string $category): Builder => $query->where('category_id', $category))
            ->when($filters['guide_type'] ?? null, fn (Builder $query, string $guideType): Builder => $query->where('guide_type_id', $guideType))
            ->when($filters['duration'] ?? null, fn (Builder $query, string $duration): Builder => $this->applyDurationFilter($query, $duration))
            ->when($filters['max_price'] ?? null, function (Builder $query, string $price): void {
                $query->whereHas('prices', fn (Builder $prices): Builder => $prices->where('price_usd', '<=', (float) $price));
            })
            ->when($filters['date'] ?? null, function (Builder $query, string $date) use ($filters): void {
                $people = max(1, (int) ($filters['people'] ?? 1));
                $query->whereHas('availabilities', fn (Builder $availability): Builder => $this->availableForPeople($availability, $date, $people));
            })
            ->when(($filters['people'] ?? null) && empty($filters['date']), function (Builder $query) use ($filters): void {
                $people = max(1, (int) $filters['people']);
                $query->whereHas('availabilities', fn (Builder $availability): Builder => $this->availableForPeople($availability, null, $people));
            })
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPublicTour(Tour $tour): Tour
    {
        abort_unless(
            $tour->status === Tour::STATUS_ACTIVE
            && $tour->review_status === Tour::REVIEW_APPROVED
            && $tour->bookings_enabled,
            404
        );

        return $tour->load([
            'category',
            'guideType',
            'transportType',
            'images',
            'prices',
            'itineraryDays.stops.activityType',
            'reviews.user',
            'availabilities' => fn ($query) => $query
                ->where('date', '>=', now()->toDateString())
                ->where('status', TourAvailability::STATUS_AVAILABLE)
                ->orderBy('date')
                ->limit(30),
            'availabilities.prices',
        ])->loadAvg('reviews', 'rating')->loadCount('reviews');
    }

    private function baseQuery(): Builder
    {
        return Tour::query()
            ->publiclyBookable()
            ->with(['category', 'guideType', 'images', 'prices'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews');
    }

    private function applyDurationFilter(Builder $query, string $duration): Builder
    {
        return match ($duration) {
            'short' => $query->where('duration', 'like', '%hora%'),
            'full_day' => $query->where(fn (Builder $query): Builder => $query->where('duration', 'like', '%dia%')->orWhere('duration', 'like', '%día%')),
            'multi_day' => $query->where(fn (Builder $query): Builder => $query->where('duration', 'like', '%dias%')->orWhere('duration', 'like', '%días%')),
            default => $query,
        };
    }

    private function availableForPeople(Builder $availability, ?string $date, int $people): Builder
    {
        return $availability
            ->where('status', TourAvailability::STATUS_AVAILABLE)
            ->when($date, fn (Builder $query): Builder => $query->whereDate('date', $date))
            ->where(function (Builder $query) use ($people): void {
                $query
                    ->whereNull('capacity')
                    ->orWhereRaw('(capacity - booked_count) >= ?', [$people]);
            });
    }

    private function normalizeDestination(string $destination): string
    {
        $destination = Str::of($destination)->after(':')->ascii()->lower()->squish()->toString();

        return str_replace(['%', '_'], ['\%', '\_'], $destination);
    }
}
