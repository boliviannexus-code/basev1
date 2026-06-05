<?php

namespace App\Http\Controllers\Web\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\AccommodationSearchRequest;
use App\Models\Space;
use App\Services\PublicSite\PublicAccommodationSearchService;
use App\Services\PublicSite\PublicReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
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
            }
        }

        return view('public.accommodations.show', [
            'filters' => $filters,
            'result' => $result,
            'quote' => $quote,
            'roomQuotes' => $roomQuotes,
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
}
