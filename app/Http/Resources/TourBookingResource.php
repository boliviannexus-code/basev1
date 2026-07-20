<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TourBookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'booking_code' => $this->booking_code,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'travel_date' => $this->travel_date?->toDateString(),
            'people' => $this->people,
            'tourist' => [
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'email' => $this->email,
                'phone' => $this->phone,
                'country' => $this->country,
            ],
            'special_requirements' => $this->special_requirements,
            'unit_price_usd' => (float) $this->unit_price_usd,
            'total_usd' => (float) $this->total_usd,
            'tour' => new TourResource($this->whenLoaded('tour')),
            'availability' => new TourAvailabilityResource($this->whenLoaded('availability')),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
