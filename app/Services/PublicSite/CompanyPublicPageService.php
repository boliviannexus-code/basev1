<?php

namespace App\Services\PublicSite;

use App\Models\AccommodationPackage;
use App\Models\Company;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class CompanyPublicPageService
{
    public function page(string $slug): array
    {
        $company = Company::query()
            ->publiclyVisible()
            ->where('public_slug', $slug)
            ->firstOrFail();

        $packages = $this->packages($company);
        $spaces = $this->spaces($company);
        $coverImage = $this->publicImageUrl($company->cover_image);
        $logo = $this->publicImageUrl($company->logo);

        return [
            'company' => $company,
            'packages' => $packages,
            'spaces' => $spaces,
            'mapLocations' => $this->mapLocations($spaces),
            'googleMapsKey' => config('services.google_maps.key'),
            'contact' => $this->contact($company),
            'seo' => [
                'title' => $company->public_name ?: $company->name,
                'description' => str($company->public_description ?: $company->name)->limit(160)->toString(),
                'image' => $coverImage ?: $logo,
                'url' => route('public.company.show', $company->public_slug),
            ],
            'media' => [
                'cover_image' => $coverImage,
                'logo' => $logo,
            ],
        ];
    }

    public function packagePage(string $companySlug, string $packageSlug): array
    {
        $company = Company::query()
            ->publiclyVisible()
            ->where('public_slug', $companySlug)
            ->firstOrFail();

        $package = AccommodationPackage::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('slug', $packageSlug)
            ->where('is_active', true)
            ->with([
                'services' => fn ($query) => $query
                    ->where('package_services.is_active', true)
                    ->orderByPivot('sort_order')
                    ->orderBy('package_services.name'),
                'spaces' => fn ($query) => $query
                    ->withoutGlobalScope('company')
                    ->where('status', 'active')
                    ->with(['location', 'photos' => fn ($photoQuery) => $photoQuery->orderBy('sort_order')])
                    ->orderByRaw('coalesce(title, name) asc'),
            ])
            ->firstOrFail();

        $displayName = $company->public_name ?: $company->name;
        $image = $this->publicImageUrl($package->main_image);

        return [
            'company' => $company,
            'package' => $package,
            'contact' => $this->contact($company),
            'gallery' => $this->packageGallery($package),
            'includedServices' => $package->services->where('pivot.inclusion_type', 'included')->values(),
            'optionalServices' => $package->services->where('pivot.inclusion_type', 'optional_paid')->values(),
            'excludedServices' => $package->services->where('pivot.inclusion_type', 'not_included')->values(),
            'category' => $package->services->first()?->type ?: collect($package->badges)->filter()->first(),
            'location' => $this->packageLocation($package),
            'packageMapLocations' => $this->mapLocations($package->spaces),
            'googleMapsKey' => config('services.google_maps.key'),
            'whatsappUrl' => $this->whatsappUrl(
                $company,
                'Hola, estoy interesado en el paquete: '.$package->name.'. Quisiera más información para la fecha: ____.'
            ),
            'reservationUrl' => $package->spaces->first()
                ? route('public.accommodations.show', ['space' => $package->spaces->first()->id, 'package_id' => $package->id])
                : null,
            'seo' => [
                'title' => $package->name.' - '.$displayName,
                'description' => str($package->short_description)->limit(160)->toString(),
                'image' => $image ?: $this->publicImageUrl($company->cover_image) ?: $this->publicImageUrl($company->logo),
                'url' => route('public.company.packages.show', [$company->public_slug, $package->slug]),
            ],
        ];
    }

    private function packages(Company $company): Collection
    {
        return AccommodationPackage::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->with([
                'services' => fn ($query) => $query
                    ->where('package_services.is_active', true)
                    ->orderByPivot('sort_order')
                    ->orderBy('package_services.name'),
                'spaces' => fn ($query) => $query
                    ->withoutGlobalScope('company')
                    ->where('status', 'active')
                    ->with(['location', 'spaceMode'])
                    ->orderByRaw('coalesce(title, name) asc'),
            ])
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (AccommodationPackage $package): AccommodationPackage {
                $package->setRelation('spaces', $package->spaces->where('status', 'active')->values());

                return $package;
            });
    }

    private function spaces(Company $company): Collection
    {
        return $company->spaces()
            ->withoutGlobalScope('company')
            ->where('status', 'active')
            ->with('location')
            ->orderByRaw('coalesce(title, name) asc')
            ->get();
    }

    private function mapLocations(Collection $spaces): Collection
    {
        return $spaces
            ->filter(fn ($space): bool => $space->location !== null)
            ->map(fn ($space): array => [
                'id' => 'space-'.$space->id,
                'name' => $space->title ?: $space->name,
                'address' => $space->location->address_text ?: $space->location->address,
                'city' => $space->location->city,
                'country' => $space->location->country,
                'reference' => $space->location->reference_text ?: $space->location->reference,
                'latitude' => $space->location->latitude,
                'longitude' => $space->location->longitude,
            ])
            ->values();
    }

    private function contact(Company $company): array
    {
        return [
            'whatsapp' => $company->whatsapp,
            'whatsapp_url' => $this->whatsappUrl($company),
            'phone' => $company->phone,
            'email' => $company->email,
            'website' => $company->website,
            'facebook_url' => $company->facebook_url,
            'instagram_url' => $company->instagram_url,
            'tiktok_url' => $company->tiktok_url,
        ];
    }

    private function whatsappUrl(Company $company, ?string $message = null): ?string
    {
        $number = preg_replace('/\D+/', '', (string) ($company->whatsapp ?: $company->phone));

        if ($number === '') {
            return null;
        }

        $message ??= 'Hola, quiero información sobre '.$company->public_name;

        return 'https://wa.me/'.$number.'?text='.rawurlencode($message);
    }

    private function publicImageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $url = Storage::disk('public')->url($path);

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://')
            ? $url
            : url($url);
    }

    private function packageGallery(AccommodationPackage $package): Collection
    {
        $gallery = collect();

        if ($package->main_image) {
            $gallery->push([
                'url' => $this->publicImageUrl($package->main_image),
                'alt' => $package->name,
            ]);
        }

        $spacePhotos = $package->spaces
            ->flatMap(fn ($space) => $space->photos->map(fn ($photo): array => [
                'url' => $this->publicImageUrl($photo->path),
                'alt' => $photo->alt_text ?: ($space->title ?: $space->name ?: $package->name),
            ]));

        return $gallery
            ->merge($spacePhotos)
            ->filter(fn (array $image): bool => filled($image['url'] ?? null))
            ->unique('url')
            ->values();
    }

    private function packageLocation(AccommodationPackage $package): string
    {
        $space = $package->spaces->first();

        if ($space?->location) {
            return collect([
                $space->location->city,
                $space->location->country,
            ])->filter()->implode(', ');
        }

        return '';
    }
}
