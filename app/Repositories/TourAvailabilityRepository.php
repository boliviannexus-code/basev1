<?php

namespace App\Repositories;

use App\Models\Tour;
use App\Models\TourAvailability;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;

class TourAvailabilityRepository
{
    public function queryAccessibleTours(): Builder
    {
        return Tour::query()
            ->when(CompanyContext::id(), fn (Builder $query, int $companyId): Builder => $query->where('company_id', $companyId));
    }

    public function firstOrCreate(Tour $tour, string $date): TourAvailability
    {
        return TourAvailability::query()->firstOrCreate(
            [
                'tour_id' => $tour->id,
                'date' => $date,
            ],
            [
                'status' => TourAvailability::STATUS_CLOSED,
                'booked_count' => 0,
            ],
        );
    }
}
