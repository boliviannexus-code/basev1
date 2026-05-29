<?php

namespace App\Repositories;

use App\Models\ActivityType;
use App\Models\Category;
use App\Models\Company;
use App\Models\GuideType;
use App\Models\Tour;
use App\Models\TourImage;
use App\Models\TransportType;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class TourRepository
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Tour::query()
            ->with(['company', 'category'])
            ->when(CompanyContext::id(), fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->latest()
            ->paginate($perPage);
    }

    public function pendingReviewPaginate(int $perPage = 15): LengthAwarePaginator
    {
        return Tour::query()
            ->with(['company', 'category'])
            ->where('review_status', Tour::REVIEW_PENDING)
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Tour
    {
        return Tour::query()->create($data)->load(['company', 'category']);
    }

    public function update(Tour $tour, array $data): Tour
    {
        $tour->update($data);

        return $tour->refresh()->load(['company', 'category', 'guideType', 'transportType', 'images', 'itineraryDays.stops.activityType']);
    }

    public function delete(Tour $tour): bool
    {
        return (bool) $tour->delete();
    }

    public function companiesForSelect(): Collection
    {
        return Company::query()
            ->when(CompanyContext::id(), fn ($query, $companyId) => $query->whereKey($companyId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function categoriesForSelect(): Collection
    {
        return Category::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);
    }

    public function guideTypesForSelect(): Collection
    {
        return GuideType::query()
            ->orderBy('title')
            ->get(['id', 'title']);
    }

    public function transportTypesForSelect(): Collection
    {
        return TransportType::query()
            ->orderBy('title')
            ->get(['id', 'title']);
    }

    public function activityTypesForSelect(): Collection
    {
        return ActivityType::query()
            ->where('is_active', true)
            ->orderBy('title')
            ->get(['id', 'title', 'icon']);
    }

    public function createImage(Tour $tour, array $data): TourImage
    {
        return $tour->images()->create($data);
    }

    public function imagesCount(Tour $tour): int
    {
        return $tour->images()->count();
    }

    public function replacePrices(Tour $tour, array $prices): void
    {
        $tour->prices()->delete();

        foreach ($prices as $price) {
            $tour->prices()->create($price);
        }
    }

    public function replaceItinerary(Tour $tour, array $days): void
    {
        $tour->itineraryDays()->delete();

        foreach ($days as $dayData) {
            $stops = $dayData['stops'] ?? [];
            unset($dayData['stops']);

            $day = $tour->itineraryDays()->create($dayData);

            foreach ($stops as $stopData) {
                $day->stops()->create($stopData);
            }
        }
    }
}
