<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\StoreInternalReservationRequest;
use App\Models\Country;
use App\Models\ExchangeRate;
use App\Models\ReservationChannel;
use App\Models\ReservationGroup;
use App\Services\Reservations\InternalReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class InternalReservationController extends Controller
{
    public function __construct(
        private readonly InternalReservationService $reservations,
    ) {}

    public function create(Request $request): View
    {
        Gate::authorize('occupancy.manage');

        $context = $this->initialContext($request);
        $checkInDate = CarbonImmutable::parse($context['check_in_date']);
        $checkOutDate = $request->filled('check_out_date')
            ? CarbonImmutable::parse((string) $request->query('check_out_date'))
            : $checkInDate->addDay();
        $reservationChannels = $this->reservationChannels($this->companyId());

        return view('reservations.internal.create', [
            'initial' => [
                ...$context,
                'check_out_date' => $checkOutDate->toDateString(),
            ],
            'resources' => $this->reservations->availableResources($this->companyId(), $checkInDate->toDateString(), $checkOutDate->toDateString()),
            'reservationChannels' => $reservationChannels,
            'defaultReservationChannel' => $this->defaultReservationChannel($reservationChannels),
            'currentExchangeRate' => ExchangeRate::currentForCompany($this->companyId()),
            'selectedBirthCountry' => $this->selectedBirthCountry($request),
            'countries' => $this->birthCountries(),
            'documentTypes' => [
                'passport' => 'Pasaporte',
                'dni' => 'DNI',
                'ci' => 'CI',
                'other' => 'Otro',
            ],
        ]);
    }

    public function availableResources(Request $request): JsonResponse
    {
        Gate::authorize('occupancy.manage');

        $data = $request->validate([
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
        ]);

        return response()->json([
            'resources' => $this->reservations->availableResources($this->companyId(), $data['check_in_date'], $data['check_out_date']),
        ]);
    }

    public function store(StoreInternalReservationRequest $request): RedirectResponse|JsonResponse
    {
        try {
            $group = $this->reservations->create($this->companyId(), $request->validated(), $request->user());
        } catch (ValidationException $exception) {
            if (! $request->expectsJson()) {
                throw $exception;
            }

            return response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first() ?: 'No se pudo crear la reserva.',
                'errors' => $exception->errors(),
            ], 422);
        }

        if (! $request->expectsJson()) {
            return redirect()
                ->to($this->occupancyUrlForGroup($group))
                ->with('success', 'Reserva '.$group->code.' creada correctamente.');
        }

        return response()->json([
            'success' => true,
            'message' => 'Reserva '.$group->code.' creada correctamente.',
            'redirect_url' => $this->occupancyUrlForGroup($group),
        ], 201);
    }

    private function initialContext(Request $request): array
    {
        $data = $request->validate([
            'check_in_date' => ['nullable', 'date'],
            'date' => ['nullable', 'date'],
            'space_id' => ['nullable', 'integer'],
            'space_room_id' => ['nullable', 'integer'],
            'room_bed_unit_id' => ['nullable', 'integer'],
            'resource_type' => ['nullable', 'in:private_space,shared_room,shared_bed_unit'],
        ]);

        $checkInDate = $data['check_in_date'] ?? $data['date'] ?? today()->toDateString();
        $resourceType = $data['resource_type'] ?? (filled($data['room_bed_unit_id'] ?? null)
            ? 'shared_bed_unit'
            : (filled($data['space_room_id'] ?? null) ? 'shared_room' : 'private_space'));

        return [
            'check_in_date' => CarbonImmutable::parse($checkInDate)->toDateString(),
            'space_id' => $data['space_id'] ?? null,
            'space_room_id' => $data['space_room_id'] ?? null,
            'room_bed_unit_id' => $data['room_bed_unit_id'] ?? null,
            'resource_type' => $resourceType,
            'check_in_type' => filled($data['space_id'] ?? null) ? 'individual' : 'multiple',
        ];
    }

    private function reservationChannels(int $companyId)
    {
        ReservationChannel::ensureDefaultsForCompany($companyId);

        return ReservationChannel::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function defaultReservationChannel($reservationChannels): ?ReservationChannel
    {
        return $reservationChannels->first(fn (ReservationChannel $channel): bool => str($channel->name)->lower()->value() === 'walk-in')
            ?: $reservationChannels->first();
    }

    private function birthCountries()
    {
        return Country::query()
            ->where('company_id', $this->companyId())
            ->forCheckInSearch()
            ->get(['id', 'name', 'iso_code']);
    }

    private function selectedBirthCountry(Request $request): ?Country
    {
        $countryId = $request->old('main_guest.birth_country_id', $request->old('birth_country_id'));

        if (! $countryId) {
            return null;
        }

        return Country::query()
            ->where('company_id', $this->companyId())
            ->whereKey($countryId)
            ->first(['id', 'name', 'iso_code']);
    }

    private function companyId(): int
    {
        return (int) auth()->user()?->company_id;
    }

    private function occupancyUrlForGroup(ReservationGroup $group): string
    {
        $group->loadMissing('reservations.space.spaceMode');
        $reservation = $group->reservations->first();
        $space = $reservation?->space;
        $params = [
            'week_start' => $group->check_in?->toDateString() ?? today()->toDateString(),
        ];

        $params['view'] = $space?->spaceMode?->slug === 'compartido'
            ? 'shared:'.$space->id
            : 'private';

        return route('occupancy.index', $params);
    }
}
