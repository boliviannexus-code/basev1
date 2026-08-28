@php
    $statusLabels = [
        'pending_payment' => 'Pendiente de pago',
        'payment_under_review' => 'Pago en revision',
        'confirmed' => 'Confirmada',
        'checked_in' => 'En check-in',
        'cancelled' => 'Cancelada',
        'no_show' => 'No show',
    ];
    $statusTones = [
        'pending_payment' => 'warning',
        'payment_under_review' => 'info',
        'confirmed' => 'success',
        'checked_in' => 'primary',
        'cancelled' => 'secondary',
        'no_show' => 'danger',
    ];
    $resourceLabel = function ($reservation): string {
        if ($reservation->bedUnitItems->isNotEmpty()) {
            return $reservation->bedUnitItems
                ->map(fn ($item) => collect([
                    $reservation->space?->title ?: $reservation->space?->name,
                    $item->bedUnit?->room?->name ?: $item->bedUnit?->room?->title,
                    $item->bedUnit?->label,
                ])->filter()->implode(' / '))
                ->implode(', ');
        }

        if ($reservation->roomItems->isNotEmpty()) {
            return $reservation->roomItems
                ->map(fn ($item) => collect([
                    $reservation->space?->title ?: $reservation->space?->name,
                    $item->room?->name ?: $item->room?->title,
                ])->filter()->implode(' / '))
                ->implode(', ');
        }

        return $reservation->space?->title ?: $reservation->space?->name ?: 'Recurso';
    };
    $isMultiple = $group->reservations->count() > 1;
    $isModal = request()->ajax();
    $nameParts = preg_split('/\s+/', trim((string) $group->guest_name), 2) ?: [];
    $firstName = old('first_name', $nameParts[0] ?? $group->guest_name);
    $lastName = old('last_name', $nameParts[1] ?? '');
@endphp

@unless ($currentExchangeRate)
    <div class="alert alert-warning">
        Configura un tipo de cambio USD a BOB para modificar precios con referencia USD.
    </div>
@endunless

<form
    method="POST"
    action="{{ route('admin.reservation-groups.update', $group) }}"
    data-ajax-form
    data-reservation-group-form
    autocomplete="off"
    novalidate
>
    @csrf
    @method('patch')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-ui.card title="Huesped titular">
                <div class="card-body">
                    <div class="row g-3">
                        <input type="hidden" name="guest_name" value="{{ old('guest_name', $group->guest_name) }}">
                        <div class="col-md-3">
                            <label class="form-label" for="document_type">Tipo documento</label>
                            <select class="form-select @error('document_type') is-invalid @enderror" id="document_type" name="document_type">
                                <option value="passport" @selected(old('document_type', $group->guest_document_type ?? 'ci') === 'passport')>Pasaporte</option>
                                <option value="dni" @selected(old('document_type', $group->guest_document_type ?? 'ci') === 'dni')>DNI</option>
                                <option value="ci" @selected(old('document_type', $group->guest_document_type ?? 'ci') === 'ci')>CI</option>
                                <option value="other" @selected(old('document_type', $group->guest_document_type ?? 'ci') === 'other')>Otro</option>
                            </select>
                            @error('document_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="guest_document">Numero documento</label>
                            <input class="form-control @error('guest_document') is-invalid @enderror" id="guest_document" name="guest_document" value="{{ old('guest_document', $group->guest_document) }}" maxlength="255">
                            @error('guest_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="birth_country_id">Pais de nacimiento</label>
                            <select class="form-select @error('birth_country_id') is-invalid @enderror" id="birth_country_id" name="birth_country_id" data-tom-select data-placeholder="Buscar pais">
                                <option value="">Sin pais</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}" @selected((string) old('birth_country_id', $group->guest_birth_country_id) === (string) $country->id)>{{ $country->name }} ({{ $country->iso_code }})</option>
                                @endforeach
                            </select>
                            @error('birth_country_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="birth_date">Fecha de nacimiento</label>
                            <input class="form-control @error('birth_date') is-invalid @enderror" id="birth_date" name="birth_date" type="date" max="{{ today()->toDateString() }}" value="{{ old('birth_date', $group->guest_birth_date?->toDateString()) }}">
                            @error('birth_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="first_name">Nombre</label>
                            <input class="form-control @error('first_name') is-invalid @enderror @error('guest_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ $firstName }}" maxlength="255" required>
                            <div class="invalid-feedback">{{ $errors->first('first_name') ?: $errors->first('guest_name') }}</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="last_name">Apellido</label>
                            <input class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ $lastName }}" maxlength="255">
                            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="guest_phone">Telefono</label>
                            <input class="form-control @error('guest_phone') is-invalid @enderror" id="guest_phone" name="guest_phone" value="{{ old('guest_phone', $group->guest_phone) }}" maxlength="255">
                            @error('guest_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="guest_email">Correo</label>
                            <input class="form-control @error('guest_email') is-invalid @enderror" id="guest_email" name="guest_email" type="email" value="{{ old('guest_email', $group->guest_email) }}" maxlength="255">
                            @error('guest_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card class="mt-3" title="Habitaciones reservadas">
                <div class="card-body">
                    @error('reservations')
                        <div class="alert alert-danger">{{ $message }}</div>
                    @enderror

                    <div class="vstack gap-3">
                        @foreach ($group->reservations as $reservation)
                            @php
                                $exchangeRate = old("reservations.{$reservation->id}.exchange_rate", $currentExchangeRate?->rate);
                                $priceBob = old("reservations.{$reservation->id}.price_per_night_bob", number_format((float) $reservation->price_per_person, 2, '.', ''));
                                $priceUsd = old("reservations.{$reservation->id}.price_per_night_usd", filled($exchangeRate) && (float) $exchangeRate > 0 ? number_format((float) $reservation->price_per_person / (float) $exchangeRate, 2, '.', '') : '');
                            @endphp
                            <div class="check-in-stay-row" data-reservation-date-row data-check-in-price-reference-row>
                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-2 mb-3">
                                    <div>
                                        <div class="fw-semibold">Estancia {{ $loop->iteration }}</div>
                                        <div class="text-body-secondary small">{{ $reservation->code }} · {{ $resourceLabel($reservation) }}</div>
                                    </div>
                                    <span class="badge bg-{{ in_array($reservation->status, ['confirmed', 'checked_in'], true) ? 'success' : ($reservation->status === 'no_show' ? 'danger' : ($reservation->status === 'cancelled' ? 'secondary' : 'warning')) }}-lt">
                                        {{ $statusLabels[$reservation->status] ?? str($reservation->status)->replace('_', ' ') }}
                                    </span>
                                </div>

                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Espacio / habitacion</label>
                                        <input class="form-control" value="{{ $resourceLabel($reservation) }}" disabled>
                                        <div class="form-hint">Para cambiar de recurso usa la opcion de mover reserva desde ocupabilidad.</div>
                                    </div>
                                    <div class="col-sm-6 col-lg-2">
                                        <label class="form-label">Personas</label>
                                        <input class="form-control @error("reservations.{$reservation->id}.guests") is-invalid @enderror" name="reservations[{{ $reservation->id }}][guests]" type="number" min="1" value="{{ old("reservations.{$reservation->id}.guests", $reservation->guests) }}">
                                        @error("reservations.{$reservation->id}.guests")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <label class="form-label">Fecha ingreso</label>
                                        <input class="form-control @error("reservations.{$reservation->id}.check_in") is-invalid @enderror" type="date" name="reservations[{{ $reservation->id }}][check_in]" value="{{ old("reservations.{$reservation->id}.check_in", $reservation->check_in->toDateString()) }}" min="{{ today()->toDateString() }}" data-reservation-check-in>
                                        @error("reservations.{$reservation->id}.check_in")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-sm-6 col-lg-3">
                                        <label class="form-label">Fecha salida</label>
                                        <input class="form-control @error("reservations.{$reservation->id}.check_out") is-invalid @enderror" type="date" name="reservations[{{ $reservation->id }}][check_out]" value="{{ old("reservations.{$reservation->id}.check_out", $reservation->check_out->toDateString()) }}" min="{{ today()->addDay()->toDateString() }}" data-reservation-check-out readonly>
                                        @error("reservations.{$reservation->id}.check_out")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-sm-6 col-lg-2">
                                        <label class="form-label">Noches</label>
                                        <input class="form-control text-end @error("reservations.{$reservation->id}.nights") is-invalid @enderror" type="number" name="reservations[{{ $reservation->id }}][nights]" value="{{ old("reservations.{$reservation->id}.nights", $reservation->nights) }}" min="1" step="1" data-reservation-nights>
                                        @error("reservations.{$reservation->id}.nights")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <input type="hidden" name="reservations[{{ $reservation->id }}][currency]" value="BOB">
                                    <input type="hidden" name="reservations[{{ $reservation->id }}][exchange_rate]" value="{{ $exchangeRate }}" data-reference-exchange-rate>
                                    <div class="col-sm-6 col-lg-4">
                                        <label class="form-label">Precio noche BOB</label>
                                        <input class="form-control @error("reservations.{$reservation->id}.price_per_night_bob") is-invalid @enderror" name="reservations[{{ $reservation->id }}][price_per_night_bob]" type="number" min="0" step="0.01" value="{{ $priceBob }}" data-reference-price-bob>
                                        @error("reservations.{$reservation->id}.price_per_night_bob")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-sm-6 col-lg-4">
                                        <label class="form-label">Precio noche USD</label>
                                        <input class="form-control @error("reservations.{$reservation->id}.price_per_night_usd") is-invalid @enderror" name="reservations[{{ $reservation->id }}][price_per_night_usd]" type="number" min="0" step="0.01" value="{{ $priceUsd }}" data-reference-price-usd>
                                        @error("reservations.{$reservation->id}.price_per_night_usd")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-lg-4">
                                        <label class="form-label d-none d-lg-block">&nbsp;</label>
                                        <label class="check-in-switch">
                                            <input class="form-check-input" type="checkbox" @checked($reservation->breakfast_included) disabled>
                                            <span>Desayuno</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </x-ui.card>
        </div>

        <div class="col-xl-4">
            <x-ui.card title="Reserva">
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Tipo de reserva</label>
                        <div class="btn-group w-100" role="group">
                            <input class="btn-check" id="reservation-type-individual" name="check_in_type" type="radio" value="individual" @checked(old('check_in_type', $isMultiple ? 'multiple' : 'individual') === 'individual')>
                            <label class="btn btn-outline-primary" for="reservation-type-individual">Individual</label>
                            <input class="btn-check" id="reservation-type-multiple" name="check_in_type" type="radio" value="multiple" @checked(old('check_in_type', $isMultiple ? 'multiple' : 'individual') === 'multiple')>
                            <label class="btn btn-outline-primary" for="reservation-type-multiple">Multiple</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="reservation_channel_id">Canal de reserva</label>
                        <select class="form-select @error('reservation_channel_id') is-invalid @enderror" id="reservation_channel_id" name="reservation_channel_id" data-tom-select data-placeholder="Seleccionar canal">
                            <option value="">Sin canal</option>
                            @foreach ($reservationChannels as $channel)
                                <option value="{{ $channel->id }}" @selected((string) old('reservation_channel_id', $group->reservation_channel_id) === (string) $channel->id)>{{ $channel->name }}</option>
                            @endforeach
                        </select>
                        @error('reservation_channel_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Cantidad total de personas</label>
                            <input class="form-control @error('total_people') is-invalid @enderror" name="total_people" type="number" min="1" value="{{ old('total_people', $group->guests) }}">
                            @error('total_people')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha ingreso</label>
                            <input class="form-control @error('check_in_date') is-invalid @enderror" name="check_in_date" type="date" min="{{ today()->toDateString() }}" value="{{ old('check_in_date', $group->check_in->toDateString()) }}" aria-describedby="reservation-global-date-hint" data-reservation-global-check-in>
                            @error('check_in_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha salida</label>
                            <input class="form-control @error('check_out_date') is-invalid @enderror" name="check_out_date" type="date" value="{{ old('check_out_date', $group->check_out->toDateString()) }}" aria-describedby="reservation-global-date-hint" data-reservation-global-check-out>
                            @error('check_out_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <div class="form-hint" id="reservation-global-date-hint">
                                <i class="ti ti-link me-1"></i><span data-reservation-global-nights>{{ $group->nights }} noches · fechas vinculadas</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Notas</label>
                        <textarea class="form-control @error('notes') is-invalid @enderror" name="notes" rows="4">{{ old('notes', $group->notes) }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="check-in-balance mt-3">
                        <div>
                            <span>Personas en recursos</span>
                            <strong>{{ $group->reservations->sum('guests') }}</strong>
                        </div>
                        <div>
                            <span>Total declarado</span>
                            <strong>{{ $group->guests }}</strong>
                        </div>
                    </div>
                </div>
                <x-slot:footer>
                    <div class="d-flex justify-content-end gap-2">
                        @if ($isModal)
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        @else
                            <a class="btn btn-outline-secondary" href="{{ route('admin.reservation-groups.show', $group) }}">Cancelar</a>
                        @endif
                        <button class="btn btn-success" type="submit">
                            <i class="ti ti-device-floppy me-1"></i>Guardar cambios
                        </button>
                    </div>
                </x-slot:footer>
            </x-ui.card>
        </div>
    </div>
</form>
