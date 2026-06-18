<?php

namespace App\Http\Controllers\Web\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\AccommodationSearchRequest;
use App\Models\Company;
use App\Models\Space;
use App\Services\PublicSite\PublicAccommodationSearchService;
use App\Services\PublicSite\PublicReservationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
            'googleMapsKey' => config('services.google_maps.key'),
            'spaceLocations' => $this->spaceLocations($results),
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
            'googleMapsKey' => config('services.google_maps.key'),
            'spaceLocations' => $this->spaceLocations($paginator->getCollection()),
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

    public function quote(AccommodationSearchRequest $request, int $space): JsonResponse
    {
        $filters = $request->validated();
        $space = Space::query()->withoutGlobalScope('company')->whereKey($space)->firstOrFail();
        $result = $this->searchService->detail($space, $filters, includePublicCalendar: false);

        abort_if($result === null, 404);

        if ($result['mode'] !== 'private') {
            return response()->json([
                'success' => false,
                'message' => 'Selecciona una habitacion o cama disponible para cotizar este alojamiento compartido.',
                'availability' => $this->availabilityPayload($result),
            ], 422);
        }

        if (! filled($filters['check_in'] ?? null) || ! filled($filters['check_out'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Selecciona fecha de ingreso y salida para calcular tu reserva.',
                'availability' => $this->availabilityPayload($result),
            ], 422);
        }

        try {
            $quote = $this->reservationService->quote([
                'space_id' => $space->id,
                ...$filters,
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first() ?: 'No pudimos cotizar esas fechas.',
                'errors' => $exception->errors(),
                'availability' => $this->availabilityPayload($result),
            ], 422);
        }

        $query = collect($filters)
            ->filter(fn ($value): bool => $value !== null && $value !== '')
            ->when(! filled($filters['package_id'] ?? null), fn (Collection $query): Collection => $query->except('package_id'))
            ->all();

        return response()->json([
            'success' => true,
            'availability' => $this->availabilityPayload($result),
            'quote' => $this->quotePayload($quote, $result, route('public.reservations.start', [
                'space_id' => $space->id,
                ...$query,
            ])),
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

    private function quotePayload(array $quote, array $result, string $reservationUrl): array
    {
        $isPackage = ($quote['booking_type'] ?? 'normal') === 'package';
        $lines = [
            ['label' => 'Ingreso', 'value' => $quote['check_in']],
            ['label' => 'Salida', 'value' => $quote['check_out']],
            ['label' => 'Personas', 'value' => (string) $quote['guests']],
            ['label' => 'Reserva con adelanto', 'value' => money_format_decimal($quote['advance_amount']).' Bs'],
            ['label' => 'Saldo', 'value' => money_format_decimal($quote['balance_amount']).' Bs'],
        ];

        if ($isPackage) {
            array_splice($lines, 3, 0, [
                ['label' => 'Precio base paquete', 'value' => money_format_decimal($quote['package_price']).' Bs'],
                [
                    'label' => 'Personas extra',
                    'value' => $quote['extra_people'].' x '.$quote['nights'].' noche'.($quote['nights'] === 1 ? '' : 's').' = '.money_format_decimal($quote['extra_people_total']).' Bs',
                ],
            ]);

            $extraPeopleDetails = collect($quote['extra_people_details'] ?? [])
                ->map(fn (array $detail): array => [
                    'label' => $detail['date'],
                    'value' => $detail['quantity'].' x '.money_format_decimal($detail['unit_price']).' Bs = '.money_format_decimal($detail['total']).' Bs',
                ])
                ->all();

            if ($extraPeopleDetails !== []) {
                array_splice($lines, 5, 0, $extraPeopleDetails);
            }

            if (($quote['package_extra_nights'] ?? 0) > 0) {
                array_splice($lines, 5 + count($extraPeopleDetails), 0, [[
                    'label' => 'Noches extra',
                    'value' => $quote['package_extra_nights'].' · '.money_format_decimal($quote['package_extra_nights_total']).' Bs',
                ]]);
            }
        }

        return [
            'booking_type' => $isPackage ? 'package' : 'normal',
            'mode_label' => $isPackage ? 'Paquete' : 'Solo habitacion',
            'title' => $isPackage ? 'Total final del paquete' : 'Total solo habitacion',
            'total' => money_format_decimal($quote['total_amount']).' Bs',
            'detail' => $isPackage
                ? 'Incluye '.$quote['included_people'].' persona'.($quote['included_people'] === 1 ? '' : 's').' · extra: '.$quote['extra_people']
                : ($result['mode'] === 'shared' ? 'Habitacion' : 'Espacio').' · '.$quote['nights'].' noche'.($quote['nights'] === 1 ? '' : 's').' · '.money_format_decimal($quote['price_per_night']).' Bs por noche',
            'lines' => $lines,
            'message' => 'Tu reserva se confirma despues de validar el pago.',
            'reservation_url' => $reservationUrl,
            'button_label' => $isPackage ? 'Reservar este paquete' : 'Reservar solo habitacion',
        ];
    }

    private function availabilityPayload(array $result): array
    {
        $calendar = collect($result['availability_calendar'] ?? []);
        $isAvailable = (bool) ($result['is_available'] ?? false);

        return [
            'is_available' => $isAvailable,
            'note' => $result['availability_note'] ?? null,
            'badge' => $calendar->isEmpty()
                ? null
                : ($isAvailable ? 'Fechas disponibles' : 'Fechas no disponibles'),
            'available_dates' => $calendar->where('status', 'available')->values()->all(),
            'unavailable_dates' => $calendar->whereIn('status', ['occupied', 'unavailable'])->values()->all(),
        ];
    }

    private function publicCompanies(): Collection
    {
        return Company::query()
            ->publiclyVisible()
            ->withCount([
                'spaces as active_spaces_count' => fn ($query) => $query->where('status', 'active')->where('is_public_online', true),
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

    private function spaceLocations(Collection $results): Collection
    {
        return $results
            ->map(function (array $result): ?array {
                $space = $result['space'];
                $location = $space->location;

                if (! $location) {
                    return null;
                }

                return [
                    'space' => $space,
                    'result' => $result,
                    'id' => 'space-'.$space->id,
                    'name' => $result['title'],
                    'address' => $location->address_text ?: $location->address,
                    'reference' => $location->reference_text ?: $location->reference,
                    'city' => $location->city,
                    'country' => $location->country,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                ];
            })
            ->filter()
            ->values();
    }

    private function companyLogoUrl(Company $company): ?string
    {
        $logoPath = $company->logo ?: $company->logo_path;

        return $logoPath ? Storage::disk('public')->url($logoPath) : null;
    }
}
