<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIns\StoreCheckInRequest;
use App\Http\Requests\CheckIns\UpdateCheckInRequest;
use App\Models\AvailabilityStatus;
use App\Models\CheckInGroup;
use App\Models\Country;
use App\Models\ExchangeRate;
use App\Models\Guest;
use App\Models\OccupancyBlock;
use App\Models\ReservationChannel;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use App\Services\CheckIn\CheckInService;
use App\Services\CheckIn\CheckInUpdateService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CheckInController extends Controller
{
    public function __construct(
        private readonly CheckInService $checkIns,
        private readonly CheckInUpdateService $updates,
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

        return view('check-ins.create', [
            'initial' => [
                ...$context,
                'check_out_date' => $checkOutDate->toDateString(),
            ],
            'resources' => $this->resourcesForDates($this->companyId(), $checkInDate, $checkOutDate),
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

    public function store(StoreCheckInRequest $request): RedirectResponse
    {
        $group = $this->checkIns->checkIn($this->companyId(), $request->validated(), $request->user());

        return redirect()
            ->route('occupancy.index')
            ->with('success', 'Check-in '.$group->code.' registrado correctamente.');
    }

    public function edit(Request $request, CheckInGroup $checkInGroup): View
    {
        Gate::authorize('occupancy.manage');
        $this->ensureOwnership($checkInGroup);

        $checkInGroup->load([
            'mainGuest',
            'reservationChannel',
            'stays.holderGuest.birthCountry',
            'stays.guests.birthCountry',
            'stays.space',
            'stays.room',
            'stays.bedUnit',
            'stays.accountStatement.items',
        ]);

        $requestedHighlightStayId = $request->integer('highlight_stay');
        $canHighlightStay = $checkInGroup->stays->count() > 1
            && $checkInGroup->stays->contains(fn ($stay): bool => (int) $stay->id === $requestedHighlightStayId);
        $highlightStayId = $canHighlightStay ? $requestedHighlightStayId : null;
        $reservationChannels = $this->reservationChannels($this->companyId());

        return view('check-ins.edit', [
            'group' => $checkInGroup,
            'highlightStayId' => $highlightStayId,
            'reservationChannels' => $reservationChannels,
            'defaultReservationChannel' => $this->defaultReservationChannel($reservationChannels),
            'currentExchangeRate' => ExchangeRate::currentForCompany($this->companyId()),
            'countries' => $this->birthCountries(),
            'documentTypes' => [
                'passport' => 'Pasaporte',
                'dni' => 'DNI',
                'ci' => 'CI',
                'other' => 'Otro',
            ],
            'guests' => Guest::query()
                ->where('company_id', $this->companyId())
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name', 'document_number']),
        ]);
    }

    public function update(UpdateCheckInRequest $request, CheckInGroup $checkInGroup): RedirectResponse
    {
        $this->ensureOwnership($checkInGroup);
        $group = $this->updates->updateGroup($checkInGroup, $request->validated(), $request->user());

        return redirect()
            ->route('check-ins.edit', $group)
            ->with('success', 'Check-in '.$group->code.' actualizado correctamente.');
    }

    public function availableResources(Request $request): JsonResponse
    {
        Gate::authorize('occupancy.manage');

        $data = $request->validate([
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
        ]);

        return response()->json([
            'resources' => $this->resourcesForDates(
                $this->companyId(),
                CarbonImmutable::parse($data['check_in_date']),
                CarbonImmutable::parse($data['check_out_date']),
            ),
        ]);
    }

    public function countryAutocomplete(Request $request): JsonResponse
    {
        Gate::authorize('occupancy.manage');

        $term = trim((string) $request->query('q', ''));

        $countries = Country::query()
            ->where('company_id', $this->companyId())
            ->forCheckInSearch()
            ->search($term)
            ->limit(20)
            ->get(['id', 'name', 'iso_code']);

        return response()->json([
            'results' => $countries->map(fn (Country $country): array => [
                'value' => $country->id,
                'text' => $country->name.' ('.$country->iso_code.')',
            ]),
        ]);
    }

    public function guestLookup(Request $request): JsonResponse
    {
        Gate::authorize('occupancy.manage');

        $data = $request->validate([
            'document_number' => ['required', 'string', 'max:80'],
        ]);

        $guest = Guest::query()
            ->with('birthCountry:id,name,iso_code')
            ->where('company_id', $this->companyId())
            ->where('document_number', trim((string) $data['document_number']))
            ->first();

        if (! $guest) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'message' => 'Datos encontrados',
            'guest' => [
                'id' => $guest->id,
                'document_type' => $guest->document_type,
                'document_number' => $guest->document_number,
                'first_name' => $guest->first_name,
                'last_name' => $guest->last_name,
                'birth_country_id' => $guest->birth_country_id,
                'birth_date' => $guest->birth_date?->toDateString(),
                'age' => $guest->age,
                'birth_country' => $guest->birthCountry ? [
                    'value' => $guest->birthCountry->id,
                    'text' => $guest->birthCountry->name.' ('.$guest->birthCountry->iso_code.')',
                ] : null,
            ],
        ]);
    }

    public function reservationChannelOptions(): JsonResponse
    {
        Gate::authorize('occupancy.manage');

        return response()->json([
            'results' => $this->reservationChannels($this->companyId())
                ->map(fn (ReservationChannel $channel): array => [
                    'value' => $channel->id,
                    'text' => $channel->name,
                ]),
        ]);
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

    private function resourcesForDates(int $companyId, CarbonImmutable $checkInDate, CarbonImmutable $checkOutDate): array
    {
        $lastNight = $checkOutDate->subDay();
        $availabilityStatuses = AvailabilityStatus::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$checkInDate->toDateString(), $lastNight->toDateString()])
            ->get();
        $blocks = OccupancyBlock::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $lastNight->toDateString())
            ->whereDate('end_date', '>=', $checkInDate->toDateString())
            ->get();

        return Space::query()
            ->with([
                'spaceMode',
                'rooms' => fn ($query) => $query
                    ->with(['beds', 'bedUnits.bedType'])
                    ->where('status', 'active')
                    ->orderBy('name'),
            ])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->orderByRaw('coalesce(title, name) asc')
            ->get()
            ->flatMap(function (Space $space) use ($availabilityStatuses, $blocks): array {
                if ($space->spaceMode?->slug === 'compartido') {
                    return $space->rooms
                        ->flatMap(function (SpaceRoom $room) use ($space, $availabilityStatuses, $blocks): array {
                            $resources = [];

                            if (in_array($room->sale_mode, ['full_room', 'flexible'], true)) {
                                $resources[] = $this->resourcePayload($space, $room, null, $availabilityStatuses, $blocks);
                            }

                            if (in_array($room->sale_mode, ['bed_unit', 'flexible'], true)) {
                                foreach ($room->bedUnits->where('status', 'active') as $bedUnit) {
                                    $resources[] = $this->resourcePayload($space, $room, $bedUnit, $availabilityStatuses, $blocks);
                                }
                            }

                            return $resources;
                        })
                        ->all();
                }

                return [$this->resourcePayload($space, null, null, $availabilityStatuses, $blocks)];
            })
            ->values()
            ->all();
    }

    private function resourcePayload(Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, $availabilityStatuses, $blocks): array
    {
        $status = $this->resourceStatus($availabilityStatuses, $blocks, $space, $room, $bedUnit);
        $spaceLabel = $space->name ?: $space->title ?: 'Alojamiento';
        $roomLabel = $room ? ($room->name ?: $room->title ?: 'Habitacion') : null;
        $fullRoomCapacity = $room ? $this->resourceCapacity($space, $room) : null;
        $fullRoomStatus = $room
            ? $this->fullRoomStatus($availabilityStatuses, $blocks, $space, $room)
            : null;
        $status = $room && ! $bedUnit && $room->sale_mode === 'flexible'
            ? $fullRoomStatus
            : $status;
        $disabledStatuses = $room && ! $bedUnit && $room->sale_mode === 'flexible'
            ? ['closed', 'occupied', 'reserved']
            : ['closed', 'occupied'];
        $label = match (true) {
            $bedUnit !== null => $spaceLabel.' / '.$roomLabel.' / '.$bedUnit->label,
            $room !== null => $spaceLabel.' / '.$roomLabel,
            default => $space->title ?: $space->name ?: 'Alojamiento',
        };

        return [
            'key' => $bedUnit ? 'bed:'.$bedUnit->id : (($room ? 'room:' : 'space:').($room?->id ?? $space->id)),
            'label' => $label,
            'resource_type' => $bedUnit ? 'shared_bed_unit' : ($room ? 'shared_room' : 'private_space'),
            'space_id' => $space->id,
            'space_room_id' => $room?->id,
            'room_bed_unit_id' => $bedUnit?->id,
            'capacity' => $bedUnit ? 1 : $this->resourceCapacity($space, $room),
            'status' => $status,
            'disabled' => in_array($status, $disabledStatuses, true),
            'sale_mode' => $room?->sale_mode,
            'full_room_key' => $room && $bedUnit && $room->sale_mode === 'flexible' ? 'room:'.$room->id : null,
            'full_room_available' => $room && $room->sale_mode === 'flexible' && $fullRoomStatus === 'available',
            'full_room_capacity' => $fullRoomCapacity,
            'full_room_status' => $fullRoomStatus,
        ];
    }

    private function resourceStatus($availabilityStatuses, $blocks, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit): string
    {
        if ($this->hasBlockingBlock($blocks, $space, $room, $bedUnit, false)) {
            return 'closed';
        }

        $statuses = $availabilityStatuses
            ->filter(fn (AvailabilityStatus $status): bool => match (true) {
                $bedUnit !== null => (
                    (int) ($status->room_bed_unit_id ?? 0) === (int) $bedUnit->id
                    || ((int) ($status->space_room_id ?? 0) === (int) $room->id && $status->room_bed_unit_id === null)
                    || ((int) $status->space_id === (int) $space->id && $status->space_room_id === null && $status->room_bed_unit_id === null)
                ),
                $room !== null => (
                    (int) ($status->space_room_id ?? 0) === (int) $room->id
                    || ((int) $status->space_id === (int) $space->id && $status->space_room_id === null && $status->room_bed_unit_id === null)
                ),
                default => (int) $status->space_id === (int) $space->id && $status->space_room_id === null && $status->room_bed_unit_id === null,
            })
            ->pluck('status');

        foreach (['occupied', 'closed', 'reserved'] as $status) {
            if ($statuses->contains($status)) {
                return $status;
            }
        }

        return 'available';
    }

    private function fullRoomStatus($availabilityStatuses, $blocks, Space $space, SpaceRoom $room): string
    {
        if ($this->hasBlockingBlock($blocks, $space, $room, null, true)) {
            return 'closed';
        }

        $statuses = $availabilityStatuses
            ->filter(fn (AvailabilityStatus $status): bool => (int) $status->space_id === (int) $space->id
                && (
                    ((int) ($status->space_room_id ?? 0) === (int) $room->id)
                    || ($status->space_room_id === null && $status->room_bed_unit_id === null)
                ))
            ->pluck('status');

        foreach (['occupied', 'closed', 'reserved'] as $status) {
            if ($statuses->contains($status)) {
                return $status;
            }
        }

        return 'available';
    }

    private function hasBlockingBlock($blocks, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, bool $includeBedUnits): bool
    {
        return $blocks->contains(function (OccupancyBlock $block) use ($space, $room, $bedUnit, $includeBedUnits): bool {
            if ((int) $block->space_id !== (int) $space->id) {
                return false;
            }

            if (! $room) {
                return $block->space_room_id === null && $block->room_bed_unit_id === null;
            }

            if ($bedUnit) {
                return (int) ($block->room_bed_unit_id ?? 0) === (int) $bedUnit->id
                    || ((int) ($block->space_room_id ?? 0) === (int) $room->id && $block->room_bed_unit_id === null)
                    || ($block->space_room_id === null && $block->room_bed_unit_id === null);
            }

            return ($block->space_room_id === null && $block->room_bed_unit_id === null)
                || ((int) ($block->space_room_id ?? 0) === (int) $room->id
                    && ($includeBedUnits || $block->room_bed_unit_id === null));
        });
    }

    private function resourceCapacity(Space $space, ?SpaceRoom $room): int
    {
        if (! $room) {
            return max((int) ($space->max_capacity ?: 1), 1);
        }

        return max(
            (int) ($room->max_capacity ?: 0)
                ?: (int) $room->beds->sum('total_capacity')
                ?: (int) $room->bedUnits->where('status', 'active')->count(),
            1,
        );
    }

    private function reservationChannels(int $companyId)
    {
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

    private function ensureOwnership(CheckInGroup $group): void
    {
        abort_unless((int) $group->company_id === $this->companyId(), 404);
    }
}
