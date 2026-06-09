<?php

namespace App\Http\Controllers\Web\AccommodationPackages;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccommodationPackages\StoreAccommodationPackageRequest;
use App\Http\Requests\AccommodationPackages\UpdateAccommodationPackageRequest;
use App\Models\AccommodationPackage;
use App\Models\PackageService;
use App\Models\Space;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AccommodationPackageController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('spaces.view'), 403);

        $packages = AccommodationPackage::query()
            ->where('company_id', $this->companyId())
            ->with(['spaces', 'services'])
            ->withCount(['spaces', 'services'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        return view('accommodation-packages.index', ['packages' => $packages]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('spaces.edit'), 403);

        return view('accommodation-packages.create', $this->formData(new AccommodationPackage([
            'currency' => 'BOB',
            'included_people' => 1,
            'requires_full_private_space' => true,
            'nights_included' => 1,
            'is_active' => true,
        ])));
    }

    public function store(StoreAccommodationPackageRequest $request): RedirectResponse
    {
        $package = AccommodationPackage::query()->create($this->packagePayload($request));
        $this->syncRelations($package, $request->validated());

        return redirect()
            ->route('accommodation-packages.index')
            ->with('success', 'Paquete todo incluido creado correctamente.');
    }

    public function edit(Request $request, AccommodationPackage $accommodationPackage): View
    {
        abort_unless($request->user()?->can('spaces.edit'), 403);
        $this->ensureOwnership($accommodationPackage);

        return view('accommodation-packages.edit', $this->formData($accommodationPackage->load(['spaces', 'services'])));
    }

    public function update(UpdateAccommodationPackageRequest $request, AccommodationPackage $accommodationPackage): RedirectResponse
    {
        $this->ensureOwnership($accommodationPackage);
        $accommodationPackage->update($this->packagePayload($request, $accommodationPackage));
        $this->syncRelations($accommodationPackage, $request->validated());

        return redirect()
            ->route('accommodation-packages.index')
            ->with('success', 'Paquete todo incluido actualizado correctamente.');
    }

    public function toggle(AccommodationPackage $accommodationPackage): RedirectResponse
    {
        abort_unless(auth()->user()?->can('spaces.edit'), 403);
        $this->ensureOwnership($accommodationPackage);

        $accommodationPackage->update(['is_active' => ! $accommodationPackage->is_active]);

        return back()->with('success', 'Estado del paquete actualizado.');
    }

    public function feature(AccommodationPackage $accommodationPackage): RedirectResponse
    {
        abort_unless(auth()->user()?->can('spaces.edit'), 403);
        $this->ensureOwnership($accommodationPackage);

        $accommodationPackage->update(['is_featured' => ! $accommodationPackage->is_featured]);

        return back()->with('success', 'Destacado del paquete actualizado.');
    }

    public function sort(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('spaces.edit'), 403);

        $data = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*' => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ($data['orders'] as $packageId => $order) {
            AccommodationPackage::query()
                ->where('company_id', $this->companyId())
                ->whereKey($packageId)
                ->update(['sort_order' => (int) ($order ?? 0)]);
        }

        return back()->with('success', 'Orden de paquetes actualizado.');
    }

    public function copy(Request $request, AccommodationPackage $accommodationPackage): RedirectResponse
    {
        abort_unless($request->user()?->can('spaces.edit'), 403);
        $this->ensureOwnership($accommodationPackage);

        $accommodationPackage->load(['spaces', 'services']);

        $copy = $accommodationPackage->replicate([
            'slug',
            'is_active',
            'is_featured',
            'created_at',
            'updated_at',
        ]);
        $copy->name = $this->copyName($accommodationPackage->name);
        $copy->slug = $this->uniqueSlug($copy->name);
        $copy->is_active = false;
        $copy->is_featured = false;
        $copy->sort_order = ((int) AccommodationPackage::query()
            ->where('company_id', $this->companyId())
            ->max('sort_order')) + 1;
        $copy->save();

        $copy->spaces()->sync($accommodationPackage->spaces->pluck('id')->all());
        $copy->services()->sync(
            $accommodationPackage->services
                ->mapWithKeys(fn (PackageService $service): array => [
                    $service->id => [
                        'inclusion_type' => $service->pivot->inclusion_type,
                        'custom_name' => $service->pivot->custom_name,
                        'custom_description' => $service->pivot->custom_description,
                        'additional_price' => $service->pivot->additional_price,
                        'sort_order' => $service->pivot->sort_order,
                    ],
                ])
                ->all(),
        );

        return redirect()
            ->route('accommodation-packages.edit', $copy)
            ->with('success', 'Creamos una copia inactiva del paquete. Revisa los datos y activala cuando este lista.');
    }

    public function destroy(Request $request, AccommodationPackage $accommodationPackage): RedirectResponse
    {
        abort_unless($request->user()?->can('spaces.edit'), 403);
        $this->ensureOwnership($accommodationPackage);

        if ($accommodationPackage->is_active) {
            return back()->with('error', 'Solo puedes eliminar paquetes inactivos. Desactivalo primero.');
        }

        $accommodationPackage->delete();

        return back()->with('success', 'Paquete eliminado correctamente.');
    }

    private function formData(AccommodationPackage $package): array
    {
        return [
            'package' => $package,
            'spaces' => $this->privateSpaces(),
            'services' => PackageService::query()
                ->where('company_id', $this->companyId())
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ];
    }

    private function packagePayload(Request $request, ?AccommodationPackage $package = null): array
    {
        $data = $request->validated();
        $imagePath = $package?->main_image;

        if ($request->hasFile('main_image')) {
            if ($imagePath) {
                Storage::disk('public')->delete($imagePath);
            }

            $imagePath = $request->file('main_image')->store('accommodation-packages', 'public');
        }

        return [
            'company_id' => $this->companyId(),
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name'], $package),
            'short_description' => $data['short_description'],
            'badges' => $this->badgesPayload($data['badges'] ?? null),
            'commercial_description' => $data['commercial_description'] ?? null,
            'conditions' => $data['conditions'] ?? null,
            'price' => $data['price'],
            'currency' => $data['currency'] ?? 'BOB',
            'price_display_text' => $data['price_display_text'] ?? null,
            'included_people' => $data['included_people'],
            'max_people' => $data['max_people'] ?? null,
            'extra_person_price' => $data['extra_person_price'] ?? null,
            'requires_full_private_space' => (bool) ($data['requires_full_private_space'] ?? true),
            'nights_included' => $data['nights_included'],
            'is_active' => (bool) ($data['is_active'] ?? false),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'sort_order' => $data['sort_order'] ?? 0,
            'main_image' => $imagePath,
            'video_url' => $data['video_url'] ?? null,
        ];
    }

    private function syncRelations(AccommodationPackage $package, array $data): void
    {
        $package->spaces()->sync($data['space_ids']);

        $servicePayload = collect($data['services'] ?? [])
            ->mapWithKeys(fn (array $service): array => [
                (int) $service['service_id'] => [
                    'inclusion_type' => $service['inclusion_type'],
                    'custom_name' => $service['custom_name'] ?? null,
                    'custom_description' => $service['custom_description'] ?? null,
                    'additional_price' => $service['additional_price'] ?? null,
                    'sort_order' => $service['sort_order'] ?? 0,
                ],
            ])
            ->all();

        $package->services()->sync($servicePayload);
    }

    private function badgesPayload(?string $badges): array
    {
        return str($badges ?? '')
            ->explode(',')
            ->map(fn ($badge) => trim((string) $badge))
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();
    }

    private function privateSpaces()
    {
        return Space::query()
            ->where('company_id', $this->companyId())
            ->whereHas('spaceMode', fn ($query) => $query->where('slug', 'privado'))
            ->orderByRaw('coalesce(title, name) asc')
            ->get();
    }

    private function uniqueSlug(string $name, ?AccommodationPackage $package = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 2;

        while (AccommodationPackage::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $this->companyId())
            ->where('slug', $slug)
            ->when($package, fn ($query) => $query->whereKeyNot($package->id))
            ->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function copyName(string $name): string
    {
        $base = $name.' - copia';
        $candidate = $base;
        $counter = 2;

        while (AccommodationPackage::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $this->companyId())
            ->where('name', $candidate)
            ->exists()) {
            $candidate = "{$base} {$counter}";
            $counter++;
        }

        return $candidate;
    }

    private function ensureOwnership(AccommodationPackage $package): void
    {
        abort_unless((int) $package->company_id === $this->companyId(), 403);
    }

    private function companyId(): int
    {
        return (int) auth()->user()?->company_id;
    }
}
