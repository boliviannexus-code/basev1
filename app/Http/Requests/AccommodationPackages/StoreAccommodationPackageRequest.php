<?php

namespace App\Http\Requests\AccommodationPackages;

use App\Models\AccommodationPackage;
use App\Models\PackageService;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccommodationPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('spaces.edit') === true;
    }

    public function rules(): array
    {
        $companyId = (int) $this->user()?->company_id;

        return [
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique('accommodation_packages', 'name')->where('company_id', $companyId),
            ],
            'short_description' => ['required', 'string', 'max:255'],
            'badges' => ['nullable', 'string', 'max:500'],
            'commercial_description' => ['nullable', 'string', 'max:5000'],
            'conditions' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'price_display_text' => ['nullable', 'string', 'max:120'],
            'included_people' => ['required', 'integer', 'min:1', 'max:100'],
            'max_people' => ['nullable', 'integer', 'min:1', 'max:100', 'gte:included_people'],
            'extra_person_price' => [
                Rule::requiredIf(fn (): bool => filled($this->input('max_people')) && (int) $this->input('max_people') > (int) $this->input('included_people')),
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],
            'requires_full_private_space' => ['sometimes', 'boolean'],
            'nights_included' => ['required', 'integer', 'min:1', 'max:365'],
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'main_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'video_url' => ['nullable', 'url', 'max:500'],
            'space_ids' => ['required', 'array', 'min:1'],
            'space_ids.*' => [
                'integer',
                Rule::exists('spaces', 'id')
                    ->where('company_id', $companyId),
            ],
            'services' => ['nullable', 'array'],
            'services.*.service_id' => [
                'required_with:services',
                'integer',
                Rule::exists('package_services', 'id')->where('company_id', $companyId),
            ],
            'services.*.inclusion_type' => ['required_with:services', Rule::in(AccommodationPackage::INCLUSION_TYPES)],
            'services.*.custom_name' => ['nullable', 'string', 'max:160'],
            'services.*.custom_description' => ['nullable', 'string', 'max:1000'],
            'services.*.additional_price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'services.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->validatePrivateSpaces($validator);
            $this->validateVideoUrl($validator);
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
            'short_description' => $this->filled('short_description') ? trim((string) $this->input('short_description')) : null,
            'badges' => $this->filled('badges') ? trim((string) $this->input('badges')) : null,
            'commercial_description' => $this->filled('commercial_description') ? trim((string) $this->input('commercial_description')) : null,
            'conditions' => $this->filled('conditions') ? trim((string) $this->input('conditions')) : null,
            'currency' => $this->filled('currency') ? strtoupper(trim((string) $this->input('currency'))) : 'BOB',
            'price_display_text' => $this->filled('price_display_text') ? trim((string) $this->input('price_display_text')) : null,
            'video_url' => $this->filled('video_url') ? trim((string) $this->input('video_url')) : null,
            'requires_full_private_space' => $this->boolean('requires_full_private_space', true),
            'is_active' => $this->boolean('is_active'),
            'is_featured' => $this->boolean('is_featured'),
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : 0,
            'space_ids' => collect($this->input('space_ids', []))
                ->filter(fn ($id): bool => filled($id))
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all(),
            'services' => collect($this->input('services', []))
                ->filter(fn (array $service): bool => filled($service['service_id'] ?? null) && filled($service['inclusion_type'] ?? null))
                ->map(function (array $service): array {
                    if (($service['inclusion_type'] ?? null) !== 'optional_paid') {
                        $service['additional_price'] = null;
                    }

                    $service['custom_name'] = null;
                    $service['custom_description'] = null;

                    return $service;
                })
                ->values()
                ->all(),
        ]);
    }

    private function validatePrivateSpaces($validator): void
    {
        $spaceIds = collect($this->input('space_ids', []));

        if ($spaceIds->isEmpty()) {
            return;
        }

        $privateCount = Space::query()
            ->where('company_id', $this->user()?->company_id)
            ->whereIn('id', $spaceIds)
            ->whereHas('spaceMode', fn ($query) => $query->where('slug', 'privado'))
            ->count();

        if ($privateCount !== $spaceIds->count()) {
            $validator->errors()->add('space_ids', 'Solo puedes asociar espacios privados de tu empresa.');
        }
    }

    private function validateVideoUrl($validator): void
    {
        $url = (string) $this->input('video_url', '');

        if ($url === '') {
            return;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowedHosts = [
            'youtube.com',
            'www.youtube.com',
            'youtu.be',
            'm.youtube.com',
            'tiktok.com',
            'www.tiktok.com',
            'vm.tiktok.com',
            'vt.tiktok.com',
            'm.tiktok.com',
        ];

        if (! in_array($host, $allowedHosts, true)) {
            $validator->errors()->add('video_url', 'El video debe ser un enlace valido de YouTube o TikTok.');
        }
    }
}
