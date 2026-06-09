<?php

namespace App\Http\Controllers\Web\AccommodationPackages;

use App\Http\Controllers\Controller;
use App\Http\Requests\AccommodationPackages\StorePackageServiceRequest;
use App\Http\Requests\AccommodationPackages\UpdatePackageServiceRequest;
use App\Models\PackageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PackageServiceController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('spaces.view'), 403);

        $type = $request->query('type');
        $services = PackageService::query()
            ->where('company_id', $this->companyId())
            ->when(filled($type), fn ($query) => $query->where('type', $type))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('package-services.index', [
            'services' => $services,
            'type' => $type,
            'types' => PackageService::TYPES,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('spaces.edit'), 403);

        return view('package-services.create', [
            'service' => new PackageService(['is_active' => true]),
            'types' => PackageService::TYPES,
        ]);
    }

    public function store(StorePackageServiceRequest $request): RedirectResponse
    {
        PackageService::query()->create([
            'company_id' => $this->companyId(),
            ...$request->validated(),
        ]);

        return redirect()
            ->route('package-services.index')
            ->with('success', 'Servicio de paquete creado correctamente.');
    }

    public function edit(Request $request, PackageService $packageService): View
    {
        abort_unless($request->user()?->can('spaces.edit'), 403);
        $this->ensureOwnership($packageService);

        return view('package-services.edit', [
            'service' => $packageService,
            'types' => PackageService::TYPES,
        ]);
    }

    public function update(UpdatePackageServiceRequest $request, PackageService $packageService): RedirectResponse
    {
        $this->ensureOwnership($packageService);
        $packageService->update($request->validated());

        return redirect()
            ->route('package-services.index')
            ->with('success', 'Servicio de paquete actualizado correctamente.');
    }

    public function toggle(PackageService $packageService): RedirectResponse
    {
        abort_unless(auth()->user()?->can('spaces.edit'), 403);
        $this->ensureOwnership($packageService);

        $packageService->update(['is_active' => ! $packageService->is_active]);

        return back()->with('success', 'Estado del servicio actualizado.');
    }

    public function sort(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('spaces.edit'), 403);

        $data = $request->validate([
            'orders' => ['required', 'array'],
            'orders.*' => ['nullable', 'integer', 'min:0'],
        ]);

        foreach ($data['orders'] as $serviceId => $order) {
            PackageService::query()
                ->where('company_id', $this->companyId())
                ->whereKey($serviceId)
                ->update(['sort_order' => (int) ($order ?? 0)]);
        }

        return back()->with('success', 'Orden de servicios actualizado.');
    }

    private function ensureOwnership(PackageService $service): void
    {
        abort_unless((int) $service->company_id === $this->companyId(), 403);
    }

    private function companyId(): int
    {
        return (int) auth()->user()?->company_id;
    }
}
