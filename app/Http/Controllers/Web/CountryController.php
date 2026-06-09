<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Countries\UpdateCountryRequest;
use App\Models\Country;
use App\Support\CountryCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('countries.manage'), 403);

        $companyId = $this->companyId();
        abort_if($companyId === null, 403);

        CountryCatalog::seedForCompany($companyId);

        $search = $request->query('search');
        $status = $request->query('status');

        $countries = Country::query()
            ->where('company_id', $companyId)
            ->search(is_string($search) ? $search : null)
            ->when($status === 'featured', fn (Builder $query): Builder => $query->where('is_featured', true))
            ->when($status === 'active', fn (Builder $query): Builder => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query): Builder => $query->where('is_active', false))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('countries.index', [
            'countries' => $countries,
            'search' => is_string($search) ? $search : '',
            'status' => is_string($status) ? $status : '',
            'featuredCount' => Country::query()->where('company_id', $companyId)->where('is_featured', true)->count(),
            'activeCount' => Country::query()->where('company_id', $companyId)->where('is_active', true)->count(),
        ]);
    }

    public function update(UpdateCountryRequest $request, Country $country): RedirectResponse
    {
        $this->ensureOwnership($country);

        $country->update($request->validated());

        return back()->with('success', 'Pais actualizado correctamente.');
    }

    public function toggleActive(Country $country): RedirectResponse
    {
        abort_unless(auth()->user()?->can('countries.manage'), 403);
        $this->ensureOwnership($country);

        $country->update(['is_active' => ! $country->is_active]);

        return back()->with('success', 'Estado del pais actualizado.');
    }

    public function toggleFeatured(Country $country): RedirectResponse
    {
        abort_unless(auth()->user()?->can('countries.manage'), 403);
        $this->ensureOwnership($country);

        $country->update(['is_featured' => ! $country->is_featured]);

        return back()->with('success', 'Pais destacado actualizado.');
    }

    private function ensureOwnership(Country $country): void
    {
        abort_unless((int) $country->company_id === $this->companyId(), 404);
    }

    private function companyId(): ?int
    {
        $companyId = auth()->user()?->company_id;

        return $companyId === null ? null : (int) $companyId;
    }
}
