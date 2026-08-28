@extends('layouts.admin')

@section('title', 'Check-in | '.config('app.name', 'Base Admin'))
@section('page-title', 'Check-in')
@section('page-subtitle', 'Registro operativo desde la grilla de ocupabilidad')

@section('content')
    @php
        $oldStays = old('stays');
        $initialResourceKey = '';
        if ($initial['resource_type'] === 'shared_bed_unit' && ($initial['room_bed_unit_id'] ?? null)) {
            $initialResourceKey = 'bed:'.$initial['room_bed_unit_id'];
        } elseif ($initial['resource_type'] === 'shared_room' && $initial['space_room_id']) {
            $initialResourceKey = 'room:'.$initial['space_room_id'];
        } elseif ($initial['space_id']) {
            $initialResourceKey = 'space:'.$initial['space_id'];
        }
        $initialStay = [
            'resource_key' => $initialResourceKey,
            'resource_type' => $initial['resource_type'],
            'space_id' => $initial['space_id'],
            'space_room_id' => $initial['space_room_id'],
            'room_bed_unit_id' => $initial['room_bed_unit_id'] ?? null,
            'people_count' => 1,
            'currency' => 'BOB',
            'exchange_rate' => $currentExchangeRate?->rate ?? '',
            'price_per_night_bob' => '',
            'price_per_night_usd' => '',
            'breakfast_included' => false,
        ];
        $initialStays = $initial['stays'] ?? [$initialStay];
        $stays = is_array($oldStays) && count($oldStays) > 0 ? $oldStays : $initialStays;
        $statusLabels = [
            'available' => 'Disponible',
            'reserved' => 'Reservado',
            'closed' => 'Cerrado',
            'occupied' => 'Ocupado',
        ];
    @endphp

    <form
        method="POST"
        action="{{ route('check-ins.store') }}"
        data-check-in-form
        data-resources='@json($resources)'
        data-available-resources-url="{{ route('check-ins.available-resources') }}"
        data-countries-url="{{ route('countries.autocomplete') }}"
        data-guest-lookup-url="{{ route('check-ins.guest-lookup') }}"
        autocomplete="off"
        novalidate
    >
        @csrf
        @if (! empty($initial['reservation_group_id']))
            <input type="hidden" name="reservation_group_id" value="{{ $initial['reservation_group_id'] }}">
            <input type="hidden" name="confirm_reserved_conversion" value="1">
        @endif

        <div class="row g-3">
            <div class="col-xl-8">
                <x-ui.card title="Huesped titular">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label" for="document_type">Tipo documento</label>
                                <select class="form-select @error('main_guest.document_type') is-invalid @enderror @error('document_type') is-invalid @enderror" id="document_type" name="document_type" required data-guest-document-type>
                                    @foreach ($documentTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('main_guest.document_type', old('document_type', $initial['main_guest']['document_type'] ?? 'passport')) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback">{{ $errors->first('main_guest.document_type') ?: $errors->first('document_type') }}</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="document_number">Numero documento</label>
                                <input class="form-control @error('main_guest.document_number') is-invalid @enderror @error('document_number') is-invalid @enderror" id="document_number" name="document_number" value="{{ old('main_guest.document_number', old('document_number', $initial['main_guest']['document_number'] ?? '')) }}" autocomplete="off" required data-guest-document-number>
                                <div class="invalid-feedback">{{ $errors->first('main_guest.document_number') ?: $errors->first('document_number') }}</div>
                                <div class="form-hint d-none" data-guest-lookup-message></div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="birth_country_id">Pais de nacimiento</label>
                                <select class="form-select @error('main_guest.birth_country_id') is-invalid @enderror @error('birth_country_id') is-invalid @enderror" id="birth_country_id" name="birth_country_id" autocomplete="new-password" data-browser-autofill-off data-country-autocomplete data-placeholder="Buscar pais" required data-main-guest-country>
                                    @if ($selectedBirthCountry)
                                        <option value="{{ $selectedBirthCountry->id }}" selected>{{ $selectedBirthCountry->name }} ({{ $selectedBirthCountry->iso_code }})</option>
                                    @endif
                                </select>
                                <div class="invalid-feedback">{{ $errors->first('main_guest.birth_country_id') ?: $errors->first('birth_country_id') }}</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="birth_date">Fecha de nacimiento</label>
                                <input class="form-control @error('main_guest.birth_date') is-invalid @enderror @error('birth_date') is-invalid @enderror" id="birth_date" name="birth_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('main_guest.birth_date', old('birth_date', $initial['main_guest']['birth_date'] ?? today()->toDateString())) }}" autocomplete="off" data-main-guest-birth-date>
                                <div class="invalid-feedback">{{ $errors->first('main_guest.birth_date') ?: $errors->first('birth_date') }}</div>
                            </div>
                            <div class="col-md-1">
                                <label class="form-label" for="guest_age">Edad</label>
                                <input class="form-control" id="guest_age" value="" autocomplete="off" readonly data-main-guest-age>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="first_name">Nombre</label>
                                <input class="form-control @error('main_guest.first_name') is-invalid @enderror @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('main_guest.first_name', old('first_name', $initial['main_guest']['first_name'] ?? '')) }}" autocomplete="new-password" data-browser-autofill-off required data-main-guest-name>
                                <div class="invalid-feedback">{{ $errors->first('main_guest.first_name') ?: $errors->first('first_name') }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="last_name">Apellido</label>
                                <input class="form-control @error('main_guest.last_name') is-invalid @enderror @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('main_guest.last_name', old('last_name', $initial['main_guest']['last_name'] ?? '')) }}" autocomplete="new-password" data-browser-autofill-off required data-main-guest-last-name>
                                <div class="invalid-feedback">{{ $errors->first('main_guest.last_name') ?: $errors->first('last_name') }}</div>
                            </div>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card class="mt-3" title="Estancias">
                    <x-slot:actions>
                        <button class="btn btn-outline-primary btn-sm" type="button" data-check-in-add-stay>
                            <i class="ti ti-plus me-1"></i>Agregar estancia
                        </button>
                    </x-slot:actions>

                    <div class="card-body">
                        <div class="alert alert-warning d-none" data-check-in-reserved-warning>
                            <i class="ti ti-alert-triangle me-1"></i>
                            Uno o mas recursos estan reservados. Confirma para convertirlos a ocupado.
                            <label class="form-check mt-2 mb-0">
                                <input class="form-check-input @error('confirm_reserved_conversion') is-invalid @enderror" name="confirm_reserved_conversion" type="checkbox" value="1" @checked(old('confirm_reserved_conversion'))>
                                <span class="form-check-label">Convertir reservado a ocupado</span>
                            </label>
                        </div>

                        @error('stays')
                            <div class="alert alert-danger">{{ $message }}</div>
                        @enderror

                        <div class="vstack gap-3" data-check-in-stays>
                            @foreach ($stays as $index => $stay)
                                @include('check-ins.partials.stay-row', [
                                    'index' => $index,
                                    'stay' => $stay,
                                    'resources' => $resources,
                                    'statusLabels' => $statusLabels,
                                    'countries' => $countries,
                                    'documentTypes' => $documentTypes,
                                    'defaultBirthCountry' => $selectedBirthCountry,
                                ])
                            @endforeach
                        </div>
                    </div>
                </x-ui.card>
            </div>

            <div class="col-xl-4">
                <x-ui.card title="Hospedaje">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Tipo de check-in</label>
                            <div class="btn-group w-100" role="group" data-check-in-type-group>
                                <input class="btn-check" id="check-in-type-individual" name="check_in_type" type="radio" value="individual" @checked(old('check_in_type', $initial['check_in_type']) === 'individual')>
                                <label class="btn btn-outline-primary" for="check-in-type-individual">Individual</label>
                                <input class="btn-check" id="check-in-type-multiple" name="check_in_type" type="radio" value="multiple" @checked(old('check_in_type', $initial['check_in_type']) === 'multiple')>
                                <label class="btn btn-outline-primary" for="check-in-type-multiple">Multiple</label>
                            </div>
                            @error('check_in_type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="reservation_channel_id">Canal de reserva</label>
                            <select class="form-select @error('reservation_channel_id') is-invalid @enderror" id="reservation_channel_id" name="reservation_channel_id" data-tom-select data-placeholder="Seleccionar canal">
                                @foreach ($reservationChannels as $channel)
                                    <option value="{{ $channel->id }}" @selected((string) old('reservation_channel_id', $initial['reservation_channel_id'] ?? $defaultReservationChannel?->id) === (string) $channel->id)>{{ $channel->name }}</option>
                                @endforeach
                            </select>
                            <div class="invalid-feedback">{{ $errors->first('reservation_channel_id') }}</div>
                        </div>

                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label" for="total_people">Cantidad total de personas</label>
                                <input class="form-control @error('total_people') is-invalid @enderror" id="total_people" name="total_people" type="number" min="1" value="{{ old('total_people', $initial['total_people'] ?? 1) }}" autocomplete="off" required data-check-in-total-people>
                                <div class="invalid-feedback">{{ $errors->first('total_people') }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="check_in_date">Fecha ingreso</label>
                                <input class="form-control @error('check_in_date') is-invalid @enderror" id="check_in_date" name="check_in_date" type="date" value="{{ old('check_in_date', $initial['check_in_date']) }}" autocomplete="off" required data-check-in-date>
                                <div class="invalid-feedback">{{ $errors->first('check_in_date') }}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="check_out_date">Fecha salida</label>
                                <input class="form-control @error('check_out_date') is-invalid @enderror" id="check_out_date" name="check_out_date" type="date" value="{{ old('check_out_date', $initial['check_out_date']) }}" autocomplete="off" required data-check-out-date>
                                <div class="invalid-feedback">{{ $errors->first('check_out_date') }}</div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label" for="notes">Notas</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="4" autocomplete="off">{{ old('notes', $initial['notes'] ?? '') }}</textarea>
                            <div class="invalid-feedback">{{ $errors->first('notes') }}</div>
                        </div>

                        <div class="check-in-balance mt-3">
                            <div>
                                <span>Personas en estancias</span>
                                <strong data-check-in-people-sum>0</strong>
                            </div>
                            <div>
                                <span>Total declarado</span>
                                <strong data-check-in-people-total>0</strong>
                            </div>
                        </div>
                    </div>
                    <x-slot:footer>
                        <div class="d-flex justify-content-end gap-2">
                            <a class="btn btn-outline-secondary" href="{{ route('occupancy.index') }}">Cancelar</a>
                            <button class="btn btn-primary" type="submit">
                                <i class="ti ti-check me-1"></i>Validar check-in
                            </button>
                        </div>
                    </x-slot:footer>
                </x-ui.card>
            </div>
        </div>

        <template data-check-in-stay-template>
            @include('check-ins.partials.stay-row', [
                'index' => '__INDEX__',
                'stay' => [
                    'resource_key' => '',
                    'resource_type' => 'private_space',
                    'space_id' => '',
                    'space_room_id' => '',
                    'room_bed_unit_id' => '',
                    'people_count' => 1,
                    'currency' => 'BOB',
                    'exchange_rate' => $currentExchangeRate?->rate ?? '',
                    'price_per_night_bob' => '',
                    'price_per_night_usd' => '',
                    'breakfast_included' => false,
                ],
                'resources' => $resources,
                'statusLabels' => $statusLabels,
                'countries' => $countries,
                'documentTypes' => $documentTypes,
                'defaultBirthCountry' => $selectedBirthCountry,
            ])
        </template>
    </form>
@endsection
