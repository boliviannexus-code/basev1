<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourAvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'status' => $this->status,
            'status_label' => $this->status_label,
            'capacity' => $this->capacity,
            'booked_count' => $this->booked_count,
            'available_spots' => $this->capacity === null ? null : max(0, $this->capacity - $this->booked_count),
            'restrictions' => $this->restrictions,
            'prices' => $this->whenLoaded('prices', fn () => $this->prices->map(fn ($price): array => [
                'id' => $price->id,
                'tour_price_id' => $price->tour_price_id,
                'title' => $price->title,
                'min_people' => $price->min_people,
                'max_people' => $price->max_people,
                'price_usd' => (float) $price->price_usd,
            ])->values()),
        ];
    }
}
