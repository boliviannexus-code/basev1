<?php

namespace App\Http\Requests\CheckIns;

use App\Models\AvailabilityStatus;
use App\Models\Country;
use App\Models\OccupancyBlock;
use App\Models\ReservationChannel;
use App\Models\ReservationGroup;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('occupancy.manage') === true
            && $this->user()?->company_id !== null;
    }

    public function rules(): array
    {
        $companyId = (int) $this->user()?->company_id;

        return [
            'check_in_type' => ['required', Rule::in(['individual', 'multiple'])],
            'main_guest.document_type' => ['required', Rule::in(['passport', 'dni', 'ci', 'other'])],
            'main_guest.document_number' => ['required', 'string', 'max:80'],
            'main_guest.first_name' => ['required', 'string', 'max:120'],
            'main_guest.last_name' => ['required', 'string', 'max:120'],
            'main_guest.birth_country_id' => [
                'required',
                Rule::exists((new Country)->getTable(), 'id')->where(fn (QueryBuilder $query): QueryBuilder => $query->where('company_id', $companyId)),
            ],
            'main_guest.birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'reservation_channel_id' => [
                'required',
                Rule::exists((new ReservationChannel)->getTable(), 'id')->where(fn (QueryBuilder $query): QueryBuilder => $query->where('company_id', $companyId)),
            ],
            'total_people' => ['required', 'integer', 'min:1'],
            'check_in_date' => ['required', 'date'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'confirm_reserved_conversion' => ['sometimes', 'boolean'],
            'reservation_group_id' => [
                'nullable',
                'integer',
                Rule::exists((new ReservationGroup)->getTable(), 'id')->where(fn (QueryBuilder $query): QueryBuilder => $query->where('company_id', $companyId)),
            ],
            'notes' => ['nullable', 'string', 'max:3000'],
            'stays' => ['required', 'array', 'min:1'],
            'stays.*.resource_type' => ['required', Rule::in(['private_space', 'shared_room', 'shared_bed_unit'])],
            'stays.*.space_id' => ['required', 'integer'],
            'stays.*.space_room_id' => ['nullable', 'integer'],
            'stays.*.room_bed_unit_id' => ['nullable', 'integer'],
            'stays.*.people_count' => ['required', 'integer', 'min:1'],
            'stays.*.price_per_night_bob' => ['nullable', 'numeric', 'min:0', 'required_without:stays.*.price_per_night_usd'],
            'stays.*.price_per_night_usd' => ['nullable', 'numeric', 'min:0', 'required_without:stays.*.price_per_night_bob'],
            'stays.*.exchange_rate' => ['nullable', 'numeric', 'min:0.0001'],
            'stays.*.currency' => ['required', Rule::in(['BOB'])],
            'stays.*.breakfast_included' => ['sometimes', 'boolean'],
            'stays.*.guests' => ['nullable', 'array'],
            'stays.*.guests.*.document_type' => ['required_with:stays.*.guests', Rule::in(['passport', 'dni', 'ci', 'other'])],
            'stays.*.guests.*.document_number' => ['required_with:stays.*.guests', 'string', 'max:80'],
            'stays.*.guests.*.first_name' => ['required_with:stays.*.guests', 'string', 'max:120'],
            'stays.*.guests.*.last_name' => ['required_with:stays.*.guests', 'string', 'max:120'],
            'stays.*.guests.*.birth_date' => ['required_with:stays.*.guests', 'date', 'before_or_equal:today'],
            'stays.*.guests.*.birth_country_id' => [
                'required_with:stays.*.guests',
                Rule::exists((new Country)->getTable(), 'id')->where(fn (QueryBuilder $query): QueryBuilder => $query->where('company_id', $companyId)),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'main_guest.document_type' => 'tipo de documento',
            'main_guest.first_name' => 'nombre',
            'main_guest.last_name' => 'apellido',
            'main_guest.birth_country_id' => 'pais de nacimiento',
            'main_guest.birth_date' => 'fecha de nacimiento',
            'reservation_channel_id' => 'canal de reserva',
            'total_people' => 'cantidad total de personas',
            'check_in_date' => 'fecha de ingreso',
            'check_out_date' => 'fecha de salida',
            'stays.*.people_count' => 'personas de la estancia',
            'stays.*.space_id' => 'espacio',
            'stays.*.space_room_id' => 'habitacion',
            'stays.*.guests.*.document_type' => 'tipo de documento del huesped',
            'stays.*.guests.*.document_number' => 'numero de documento del huesped',
            'stays.*.guests.*.first_name' => 'nombre del huesped',
            'stays.*.guests.*.last_name' => 'apellido paterno del huesped',
            'stays.*.guests.*.birth_date' => 'fecha de nacimiento del huesped',
            'stays.*.guests.*.birth_country_id' => 'pais de nacimiento del huesped',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $companyId = (int) $this->user()?->company_id;
            $stays = $this->input('stays', []);
            $totalPeople = (int) $this->input('total_people');
            $peopleInStays = collect($stays)->sum(fn (array $stay): int => (int) ($stay['people_count'] ?? 0));

            if ($peopleInStays > $totalPeople) {
                $validator->errors()->add('stays', 'La suma de personas por estancia no puede superar la cantidad total.');
            }

            foreach ($stays as $index => $stay) {
                $currency = $stay['currency'] ?? 'BOB';
                $hasBob = filled($stay['price_per_night_bob'] ?? null);
                $hasUsd = filled($stay['price_per_night_usd'] ?? null);

                if (! $hasBob && ! $hasUsd) {
                    $validator->errors()->add("stays.{$index}.price_per_night_bob", 'Ingresa el precio por noche en BOB o USD.');
                }

                if ((! $hasBob || ! $hasUsd) && ! filled($stay['exchange_rate'] ?? null)) {
                    $validator->errors()->add('stays', 'Configura un tipo de cambio vigente antes de registrar check-in.');
                }

                $additionalGuests = count($stay['guests'] ?? []);
                $peopleCount = (int) ($stay['people_count'] ?? 0);

                if ($additionalGuests > max($peopleCount - 1, 0)) {
                    $validator->errors()->add("stays.{$index}.guests", 'La cantidad de acompañantes no puede superar las personas de la estancia. El titular ya esta incluido.');
                }

                foreach ($stay['guests'] ?? [] as $guestIndex => $guest) {
                    foreach ($this->requiredAdditionalGuestFields() as $field) {
                        if (! filled($guest[$field] ?? null)) {
                            $validator->errors()->add("stays.{$index}.guests.{$guestIndex}.{$field}", 'Completa este dato del huesped.');
                        }
                    }
                }

                $this->validateStayResource($validator, $companyId, (int) $index, $stay);
            }
        });
    }

    protected function requiredAdditionalGuestFields(): array
    {
        return ['document_type', 'document_number', 'first_name', 'last_name', 'birth_date', 'birth_country_id'];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'main_guest' => [
                'document_type' => $this->filled('main_guest.document_type') ? trim((string) $this->input('main_guest.document_type')) : trim((string) $this->input('document_type', '')),
                'document_number' => $this->filled('main_guest.document_number') || $this->filled('document_number')
                    ? trim((string) $this->input('main_guest.document_number', $this->input('document_number')))
                    : null,
                'first_name' => $this->filled('main_guest.first_name') || $this->filled('first_name')
                    ? $this->capitalizeHumanText((string) $this->input('main_guest.first_name', $this->input('first_name', '')))
                    : '',
                'last_name' => $this->filled('main_guest.last_name') || $this->filled('last_name')
                    ? $this->capitalizeHumanText((string) $this->input('main_guest.last_name', $this->input('last_name', '')))
                    : '',
                'birth_country_id' => filled($this->input('main_guest.birth_country_id', $this->input('birth_country_id')))
                    ? (int) $this->input('main_guest.birth_country_id', $this->input('birth_country_id'))
                    : null,
                'birth_date' => $this->filled('main_guest.birth_date') || $this->filled('birth_date')
                    ? $this->input('main_guest.birth_date', $this->input('birth_date'))
                    : null,
            ],
            'total_people' => $this->filled('total_people') ? (int) $this->input('total_people') : null,
            'reservation_group_id' => $this->filled('reservation_group_id') ? (int) $this->input('reservation_group_id') : null,
            'confirm_reserved_conversion' => $this->boolean('confirm_reserved_conversion'),
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
            'stays' => collect($this->input('stays', []))
                ->map(fn (array $stay): array => [
                    ...$stay,
                    'currency' => 'BOB',
                    'space_id' => filled($stay['space_id'] ?? null) ? (int) $stay['space_id'] : null,
                    'space_room_id' => filled($stay['space_room_id'] ?? null) ? (int) $stay['space_room_id'] : null,
                    'room_bed_unit_id' => filled($stay['room_bed_unit_id'] ?? null) ? (int) $stay['room_bed_unit_id'] : null,
                    'people_count' => filled($stay['people_count'] ?? null) ? (int) $stay['people_count'] : null,
                    'breakfast_included' => filter_var($stay['breakfast_included'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'guests' => collect($stay['guests'] ?? [])
                        ->filter(fn (array $guest): bool => filled($guest['first_name'] ?? null)
                            || filled($guest['last_name'] ?? null)
                            || filled($guest['birth_date'] ?? null)
                            || filled($guest['birth_country_id'] ?? null)
                            || filled($guest['document_type'] ?? null)
                            || filled($guest['document_number'] ?? null))
                        ->map(fn (array $guest): array => [
                            'document_type' => filled($guest['document_type'] ?? null) ? trim((string) $guest['document_type']) : 'passport',
                            'document_number' => filled($guest['document_number'] ?? null) ? trim((string) $guest['document_number']) : null,
                            'first_name' => filled($guest['first_name'] ?? null) ? $this->capitalizeHumanText((string) $guest['first_name']) : null,
                            'last_name' => filled($guest['last_name'] ?? null) ? $this->capitalizeHumanText((string) $guest['last_name']) : null,
                            'birth_date' => filled($guest['birth_date'] ?? null) ? $guest['birth_date'] : null,
                            'birth_country_id' => filled($guest['birth_country_id'] ?? null) ? (int) $guest['birth_country_id'] : null,
                        ])
                        ->values()
                        ->all(),
                ])
                ->all(),
        ]);
    }

    private function capitalizeHumanText(string $value): string
    {
        return Str::of($value)->squish()->title()->toString();
    }

    private function validateStayResource(Validator $validator, int $companyId, int $index, array $stay): void
    {
        $space = Space::query()
            ->with('spaceMode')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereKey($stay['space_id'] ?? null)
            ->first();

        if (! $space) {
            $validator->errors()->add("stays.{$index}.space_id", 'El espacio seleccionado no esta activo.');

            return;
        }

        $resourceType = $stay['resource_type'] ?? null;
        $isShared = $space->spaceMode?->slug === 'compartido';
        $room = null;
        $bedUnit = null;
        $capacity = (int) ($space->max_capacity ?: 1);

        if ($resourceType === 'private_space' && $isShared) {
            $validator->errors()->add("stays.{$index}.resource_type", 'Selecciona una habitacion para alojamientos compartidos.');
        }

        if (in_array($resourceType, ['shared_room', 'shared_bed_unit'], true)) {
            $room = SpaceRoom::query()
                ->with(['beds', 'bedUnits'])
                ->where('company_id', $companyId)
                ->where('space_id', $space->id)
                ->where('status', 'active')
                ->whereKey($stay['space_room_id'] ?? null)
                ->first();

            if (! $room) {
                $validator->errors()->add("stays.{$index}.space_room_id", 'La habitacion seleccionada no esta activa.');

                return;
            }

            $capacity = max(
                (int) ($room->max_capacity ?: 0)
                    ?: (int) $room->beds->sum('total_capacity')
                    ?: (int) $room->bedUnits->where('status', 'active')->count(),
                1,
            );
        }

        if ($resourceType === 'shared_room' && ! in_array($room?->sale_mode, ['full_room', 'flexible'], true)) {
            $validator->errors()->add("stays.{$index}.resource_type", 'Esta habitacion no permite venta como habitacion completa.');

            return;
        }

        if ($resourceType === 'shared_bed_unit') {
            if (! in_array($room?->sale_mode, ['bed_unit', 'flexible'], true)) {
                $validator->errors()->add("stays.{$index}.resource_type", 'Esta habitacion no permite venta por cama.');

                return;
            }

            $bedUnit = RoomBedUnit::query()
                ->where('company_id', $companyId)
                ->where('space_room_id', $room->id)
                ->where('status', 'active')
                ->whereKey($stay['room_bed_unit_id'] ?? null)
                ->first();

            if (! $bedUnit) {
                $validator->errors()->add("stays.{$index}.room_bed_unit_id", 'La cama seleccionada no esta activa.');

                return;
            }

            $capacity = 1;
        }

        if ((int) ($stay['people_count'] ?? 0) > $capacity) {
            $validator->errors()->add("stays.{$index}.people_count", "La capacidad maxima de este recurso es {$capacity}.");
        }

        $this->validateAvailability($validator, $companyId, $index, $space, $room, $bedUnit);
    }

    private function validateAvailability(Validator $validator, int $companyId, int $index, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit): void
    {
        $checkIn = CarbonImmutable::parse((string) $this->input('check_in_date'));
        $checkOut = CarbonImmutable::parse((string) $this->input('check_out_date'));
        $lastNight = $checkOut->subDay();

        $hasBlock = OccupancyBlock::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $lastNight->toDateString())
            ->whereDate('end_date', '>=', $checkIn->toDateString())
            ->when($this->filled('reservation_group_id'), fn (Builder $query): Builder => $this->excludeReservationGroupBlocks($query, (int) $this->input('reservation_group_id')))
            ->when(
                $bedUnit,
                fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                    ->where('room_bed_unit_id', $bedUnit->id)
                    ->orWhere(fn (Builder $query): Builder => $query->where('space_room_id', $room->id)->whereNull('room_bed_unit_id'))
                    ->orWhere(fn (Builder $query): Builder => $query->where('space_id', $space->id)->whereNull('space_room_id'))),
                fn (Builder $query): Builder => $query->when(
                    $room,
                    fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                        ->where('space_room_id', $room->id)
                        ->orWhere(fn (Builder $query): Builder => $query->where('space_id', $space->id)->whereNull('space_room_id'))),
                    fn (Builder $query): Builder => $query->where('space_id', $space->id)->whereNull('space_room_id'),
                ),
            )
            ->exists();

        if ($hasBlock) {
            $validator->errors()->add("stays.{$index}.space_id", 'El recurso ya tiene un bloqueo u ocupacion en esas fechas.');

            return;
        }

        $blockedStatuses = AvailabilityStatus::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$checkIn->toDateString(), $lastNight->toDateString()])
            ->when(
                $bedUnit,
                fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                    ->where('room_bed_unit_id', $bedUnit->id)
                    ->orWhere(fn (Builder $query): Builder => $query->where('space_room_id', $room->id)->whereNull('room_bed_unit_id'))
                    ->orWhere(fn (Builder $query): Builder => $query->where('space_id', $space->id)->whereNull('space_room_id')->whereNull('room_bed_unit_id'))),
                fn (Builder $query): Builder => $query->when(
                    $room,
                    fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query
                        ->where('space_room_id', $room->id)
                        ->orWhere(fn (Builder $query): Builder => $query->where('space_id', $space->id)->whereNull('space_room_id')->whereNull('room_bed_unit_id'))),
                    fn (Builder $query): Builder => $query->where('space_id', $space->id)->whereNull('space_room_id')->whereNull('room_bed_unit_id'),
                ),
            )
            ->whereIn('status', ['closed', 'occupied'])
            ->pluck('status')
            ->all();

        if ($blockedStatuses !== []) {
            $validator->errors()->add("stays.{$index}.space_id", 'No se puede seleccionar un recurso cerrado u ocupado.');

            return;
        }

        if ($room && ! $bedUnit && $room->sale_mode === 'flexible') {
            $bedBlockedStatuses = AvailabilityStatus::query()
                ->where('company_id', $companyId)
                ->whereBetween('date', [$checkIn->toDateString(), $lastNight->toDateString()])
                ->where('space_id', $space->id)
                ->where('space_room_id', $room->id)
                ->whereNotNull('room_bed_unit_id')
                ->whereIn('status', ['closed', 'occupied', 'reserved'])
                ->exists();

            if ($bedBlockedStatuses) {
                $validator->errors()->add("stays.{$index}.space_id", 'No se puede vender la habitacion completa porque una cama no esta libre.');
            }
        }
    }

    private function excludeReservationGroupBlocks(Builder $query, int $reservationGroupId): Builder
    {
        return $query
            ->whereDoesntHave('reservation', fn (Builder $reservation): Builder => $reservation->where('reservation_group_id', $reservationGroupId))
            ->whereDoesntHave('reservationRoom.reservation', fn (Builder $reservation): Builder => $reservation->where('reservation_group_id', $reservationGroupId))
            ->whereDoesntHave('reservationBedUnit.reservation', fn (Builder $reservation): Builder => $reservation->where('reservation_group_id', $reservationGroupId));
    }
}
