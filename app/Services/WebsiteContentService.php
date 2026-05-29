<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Tour;
use App\Models\WebsiteSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class WebsiteContentService
{
    public function settings(): WebsiteSetting
    {
        return WebsiteSetting::query()->firstOrCreate([], [
            'hero_eyebrow' => 'Experiencias locales verificadas',
            'hero_title' => 'Reserva tours memorables con guias locales',
            'hero_subtitle' => 'Encuentra disponibilidad real, compara experiencias y confirma tu proxima aventura en pocos pasos.',
            'popup_enabled' => false,
            'show_companies' => true,
        ]);
    }

    public function update(array $data): WebsiteSetting
    {
        $settings = $this->settings();

        foreach (['logo_path' => 'logo', 'hero_image_path' => 'hero_image', 'popup_image_path' => 'popup_image'] as $column => $input) {
            if (($data[$input] ?? null) instanceof UploadedFile) {
                $this->deleteFile($settings->{$column});
                $data[$column] = $data[$input]->store('website', 'public');
            }

            unset($data[$input]);
        }

        foreach (['remove_logo' => 'logo_path', 'remove_hero_image' => 'hero_image_path', 'remove_popup_image' => 'popup_image_path'] as $flag => $column) {
            if (! empty($data[$flag])) {
                $this->deleteFile($settings->{$column});
                $data[$column] = null;
            }

            unset($data[$flag]);
        }

        $data['popup_enabled'] = (bool) ($data['popup_enabled'] ?? false);
        $data['show_companies'] = (bool) ($data['show_companies'] ?? false);
        $data['featured_tour_ids'] = collect($data['featured_tour_ids'] ?? [])
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $settings->update($data);

        return $settings->refresh();
    }

    public function featuredTours(): Collection
    {
        $ids = collect($this->settings()->featured_tour_ids ?? [])->filter()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Tour::query()
            ->publiclyBookable()
            ->with(['category', 'guideType', 'images', 'prices'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn (Tour $tour): int => $ids->search($tour->id))
            ->values();
    }

    public function visibleCompanies(int $limit = 8): Collection
    {
        if (! $this->settings()->show_companies) {
            return collect();
        }

        return Company::query()
            ->where('is_active', true)
            ->whereHas('tours', fn ($query) => $query->publiclyBookable())
            ->withCount(['tours' => fn ($query) => $query->publiclyBookable()])
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    public function toursForSelect(): Collection
    {
        return Tour::query()
            ->publiclyBookable()
            ->orderBy('title')
            ->get(['id', 'title', 'name', 'city']);
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
