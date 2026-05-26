<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWebsiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('website.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'logo' => ['nullable', 'image', 'max:4096'],
            'hero_image' => ['nullable', 'image', 'max:6144'],
            'popup_image' => ['nullable', 'image', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_hero_image' => ['nullable', 'boolean'],
            'remove_popup_image' => ['nullable', 'boolean'],
            'hero_eyebrow' => ['nullable', 'string', 'max:120'],
            'hero_title' => ['nullable', 'string', 'max:180'],
            'hero_subtitle' => ['nullable', 'string', 'max:600'],
            'popup_enabled' => ['nullable', 'boolean'],
            'popup_title' => ['nullable', 'string', 'max:160'],
            'popup_body' => ['nullable', 'string', 'max:800'],
            'popup_cta_label' => ['nullable', 'string', 'max:80'],
            'popup_cta_url' => ['nullable', 'string', 'max:255'],
            'featured_tour_ids' => ['nullable', 'array'],
            'featured_tour_ids.*' => ['nullable', 'integer', 'exists:tours,id'],
            'show_companies' => ['nullable', 'boolean'],
        ];
    }
}
