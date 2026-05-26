<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class WebsiteSetting extends Model
{
    protected $fillable = [
        'logo_path',
        'hero_image_path',
        'hero_eyebrow',
        'hero_title',
        'hero_subtitle',
        'popup_enabled',
        'popup_title',
        'popup_body',
        'popup_cta_label',
        'popup_cta_url',
        'popup_image_path',
        'featured_tour_ids',
        'show_companies',
    ];

    protected function casts(): array
    {
        return [
            'popup_enabled' => 'boolean',
            'featured_tour_ids' => 'array',
            'show_companies' => 'boolean',
        ];
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function getHeroImageUrlAttribute(): ?string
    {
        return $this->hero_image_path ? Storage::disk('public')->url($this->hero_image_path) : null;
    }

    public function getPopupImageUrlAttribute(): ?string
    {
        return $this->popup_image_path ? Storage::disk('public')->url($this->popup_image_path) : null;
    }
}
