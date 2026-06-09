<?php

namespace App\Http\Controllers\Web\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\AccommodationSearchRequest;
use App\Models\Company;
use App\Models\Space;
use App\Services\PublicSite\PublicAccommodationSearchService;
use App\Services\PublicSite\PublicReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PublicAccommodationController extends Controller
{
    public function __construct(
        private readonly PublicAccommodationSearchService $searchService,
        private readonly PublicReservationService $reservationService,
    ) {}

    public function index(AccommodationSearchRequest $request): View
    {
        $filters = $request->validated();
        $results = $this->searchService->featured($filters);

        return view('public.accommodations.index', [
            'filters' => $filters,
            'results' => $results,
            'paginator' => null,
            'isSearch' => false,
            'publicCompanies' => $this->publicCompanies(),
        ]);
    }

    public function search(AccommodationSearchRequest $request): View
    {
        $filters = $request->validated();
        $paginator = $this->searchService->search($filters);

        return view('public.accommodations.index', [
            'filters' => $filters,
            'results' => $paginator->getCollection(),
            'paginator' => $paginator,
            'isSearch' => true,
            'publicCompanies' => collect(),
        ]);
    }

    public function show(AccommodationSearchRequest $request, int $space): View
    {
        $filters = $request->validated();
        $space = Space::query()->withoutGlobalScope('company')->whereKey($space)->firstOrFail();
        $result = $this->searchService->detail($space, $filters);

        abort_if($result === null, 404);

        $quote = null;
        $roomQuotes = collect();
        $bedUnitQuotes = collect();

        if (filled($filters['check_in'] ?? null) && filled($filters['check_out'] ?? null)) {
            if ($result['mode'] === 'private') {
                $quote = $this->safeQuote([
                    'space_id' => $space->id,
                    ...$filters,
                ]);
            } else {
                $roomQuotes = $result['rooms']
                    ->mapWithKeys(fn ($room): array => [
                        $room->id => $this->safeQuote([
                            'space_id' => $space->id,
                            'space_room_ids' => [$room->id],
                            ...$filters,
                        ]),
                    ])
                    ->filter();
                $bedUnitQuotes = $result['rooms']
                    ->flatMap(function ($room) {
                        if (! in_array($room->sale_mode, ['bed_unit', 'flexible'], true)) {
                            return collect();
                        }

                        return $room->relationLoaded('availableBedUnits')
                            ? $room->getRelation('availableBedUnits')
                            : $room->bedUnits->where('status', 'active')->values();
                    })
                    ->mapWithKeys(fn ($unit): array => [
                        $unit->id => $this->safeQuote([
                            'space_id' => $space->id,
                            'room_bed_unit_ids' => [$unit->id],
                            ...$filters,
                        ]),
                    ])
                    ->filter();
            }
        }

        return view('public.accommodations.show', [
            'filters' => $filters,
            'result' => $result,
            'quote' => $quote,
            'roomQuotes' => $roomQuotes,
            'bedUnitQuotes' => $bedUnitQuotes,
        ]);
    }

    public function legacySearch(): RedirectResponse
    {
        return redirect()->route('public.accommodations.search', request()->query());
    }

    public function legacyShow(int $space): RedirectResponse
    {
        return redirect()->route('public.accommodations.show', [
            'space' => $space,
            ...request()->query(),
        ]);
    }

    private function safeQuote(array $data): ?array
    {
        try {
            return $this->reservationService->quote($data);
        } catch (ValidationException) {
            return null;
        }
    }

    private function publicCompanies(): Collection
    {
        return Company::query()
            ->publiclyVisible()
            ->withCount([
                'spaces as active_spaces_count' => fn ($query) => $query->where('status', 'active'),
                'accommodationPackages as active_accommodation_packages_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderByRaw('COALESCE(public_name, name)')
            ->get()
            ->map(fn (Company $company): array => [
                'name' => $company->public_name ?: $company->name,
                'slug' => $company->public_slug,
                'description' => $company->public_description,
                'cover_url' => $company->cover_image ? Storage::disk('public')->url($company->cover_image) : null,
                'logo_url' => $this->companyLogoUrl($company),
                'location' => collect([$company->city, $company->country])->filter()->implode(', '),
                'active_spaces_count' => (int) $company->active_spaces_count,
                'active_accommodation_packages_count' => (int) $company->active_accommodation_packages_count,
            ]);
    }

    private function companyLogoUrl(Company $company): ?string
    {
        $logoPath = $company->logo ?: $company->logo_path;

        return $logoPath ? Storage::disk('public')->url($logoPath) : null;
    }
}
