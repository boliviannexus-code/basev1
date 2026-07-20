<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebsiteContentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $settings = $this->resource['settings'];
        $featuredTours = $this->resource['featured_tours'];
        $companies = $this->resource['companies'];

        return [
            'logo_url' => $settings->logo_url,
            'hero' => [
                'image_url' => $settings->hero_image_url,
                'eyebrow' => $settings->hero_eyebrow,
                'title' => $settings->hero_title,
                'subtitle' => $settings->hero_subtitle,
            ],
            'popup' => [
                'enabled' => $settings->popup_enabled,
                'title' => $settings->popup_title,
                'body' => $settings->popup_body,
                'cta_label' => $settings->popup_cta_label,
                'cta_url' => $settings->popup_cta_url,
                'image_url' => $settings->popup_image_url,
            ],
            'featured_tours' => TourResource::collection($featuredTours),
            'companies' => $companies->map(fn ($company): array => [
                'id' => $company->id,
                'name' => $company->name,
                'city' => $company->city,
                'country' => $company->country,
                'logo_url' => $company->logo_url,
                'tours_count' => $company->tours_count,
            ])->values(),
        ];
    }
}
