@extends('layouts.admin')

@section('title', 'Editar check-in '.$group->code.' | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar check-in '.$group->code)
@section('page-subtitle', 'Ajuste de estancias, noches, cuentas y disponibilidad')

@section('content')
    @php
        $statusBadge = [
            'checked_in' => 'text-bg-primary',
            'checked_out' => 'text-bg-success',
            'cancelled' => 'text-bg-secondary',
        ][$group->status] ?? 'text-bg-primary';
    @endphp

    <div class="check-in-edit-shell">
        <x-ui.card>
            <div class="card-body py-3">
                <div class="check-in-edit-summary">
                    <div class="check-in-edit-summary-main">
                        <div>
                            <div class="text-body-secondary small">Titular</div>
                            <div class="fw-semibold">
                                {{ $group->mainGuest?->full_name }}
                                @if ($group->mainGuest?->age !== null)
                                    <span class="text-body-secondary fw-normal">({{ $group->mainGuest->age }} anos)</span>
                                @endif
                            </div>
                        </div>
                        <div>
                            <div class="text-body-secondary small">Ingreso</div>
                            <div class="fw-semibold">{{ $group->check_in_date->toDateString() }}</div>
                        </div>
                        <div>
                            <div class="text-body-secondary small">Salida grupo</div>
                            <div class="fw-semibold">{{ $group->check_out_date->toDateString() }}</div>
                        </div>
                        <div>
                            <div class="text-body-secondary small">Estado</div>
                            <span class="badge {{ $statusBadge }}">{{ $group->status }}</span>
                        </div>
                    </div>

                    <div class="check-in-edit-summary-controls">
                        <div>
                            <div class="text-body-secondary small">Canal</div>
                            <div class="fw-semibold">{{ $group->reservationChannel?->name ?? 'Sin canal' }}</div>
                        </div>
                        <div>
                            <div class="text-body-secondary small">Notas generales</div>
                            <div class="fw-semibold">{{ filled($group->notes) ? $group->notes : 'Sin notas generales' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui.card>

        <div class="check-in-edit-stays">
            @foreach ($group->stays as $stay)
                @php
                    $resourceLabel = collect([
                        $stay->space?->name ?: $stay->space?->title,
                        $stay->room?->name ?: $stay->room?->title,
                        $stay->bedUnit?->label,
                    ])->filter()->implode(' / ');
                    $holderName = trim(($stay->holderGuest?->first_name ?? '').' '.($stay->holderGuest?->last_name ?? '')) ?: 'Sin titular';
                    $stayGuests = $stay->guests->sortByDesc(fn ($guest) => (bool) $guest->pivot?->is_holder);
                    $additionalGuests = old('guests', $stay->guests
                        ->filter(fn ($guest) => ! (bool) $guest->pivot?->is_holder)
                        ->map(fn ($guest) => [
                            'id' => $guest->id,
                            'document_type' => $guest->document_type,
                            'document_number' => $guest->document_number,
                            'first_name' => $guest->first_name,
                            'last_name' => $guest->last_name,
                            'birth_date' => $guest->birth_date?->toDateString(),
                            'birth_country_id' => $guest->birth_country_id,
                        ])
                        ->values()
                        ->all());
                    $hasAdditionalGuests = count($additionalGuests) > 0;
                    $isHighlightedStay = (int) ($highlightStayId ?? 0) === (int) $stay->id;
                    $activeItems = $stay->accountStatement?->items->where('status', 'active')->where('type', 'lodging_night') ?? collect();
                    $cancelledItems = $stay->accountStatement?->items->where('status', 'cancelled')->where('type', 'lodging_night') ?? collect();
                @endphp

                <x-ui.card
                    id="stay-{{ $stay->id }}"
                    class="check-in-edit-stay-card {{ $isHighlightedStay ? 'is-origin' : '' }}"
                >
                    <div class="card-body py-3">
                        <div class="check-in-edit-stay-head">
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <div class="fw-semibold">Estancia {{ $loop->iteration }} · {{ $resourceLabel }}</div>
                                    @if ($isHighlightedStay)
                                        <span class="badge bg-primary-lt">Seleccionada desde grilla</span>
                                    @endif
                                </div>
                                <div class="text-body-secondary small">Titular: {{ $holderName }}</div>
                            </div>
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('occupancy.index', ['week_start' => $stay->check_in_date->toDateString(), 'space_id' => $stay->space_id]) }}">
                                <i class="ti ti-calendar-stats me-1"></i>Grilla
                            </a>
                        </div>

                        <form class="check-in-edit-stay-form" method="post" action="{{ route('stays.update', $stay) }}" data-guest-lookup-url="{{ route('check-ins.guest-lookup') }}" autocomplete="off" data-check-in-price-reference-row>
                            @csrf
                            @method('patch')

                            <div>
                                <label class="form-label">Ingreso</label>
                                <input class="form-control form-control-sm" value="{{ $stay->check_in_date->toDateString() }}" autocomplete="off" disabled>
                            </div>
                            <div>
                                <label class="form-label" for="stay-{{ $stay->id }}-check-out">Salida</label>
                                <input class="form-control form-control-sm @error('check_out_date') is-invalid @enderror" id="stay-{{ $stay->id }}-check-out" name="check_out_date" type="date" value="{{ old('check_out_date', $stay->check_out_date->toDateString()) }}" autocomplete="off" @disabled($stay->status !== 'occupied') required>
                                <div class="invalid-feedback">{{ $errors->first('check_out_date') }}</div>
                            </div>
                            <div>
                                <label class="form-label">Personas</label>
                                <input class="form-control form-control-sm @error('people_count') is-invalid @enderror" name="people_count" type="number" min="1" value="{{ old('people_count', $stay->people_count) }}" autocomplete="off" @disabled($stay->status !== 'occupied') required>
                                <div class="invalid-feedback">{{ $errors->first('people_count') }}</div>
                            </div>
                            <input type="hidden" name="currency" value="BOB">
                            <div>
                                <label class="form-label">Precio BOB</label>
                                <input class="form-control form-control-sm @error('price_per_night_bob') is-invalid @enderror" name="price_per_night_bob" type="number" min="0" step="0.01" value="{{ old('price_per_night_bob', $stay->price_per_night_bob) }}" autocomplete="off" data-reference-price-bob @disabled($stay->status !== 'occupied')>
                                <div class="invalid-feedback">{{ $errors->first('price_per_night_bob') }}</div>
                            </div>
                            <div>
                                <label class="form-label">Precio USD</label>
                                <input class="form-control form-control-sm @error('price_per_night_usd') is-invalid @enderror" name="price_per_night_usd" type="number" min="0" step="0.01" value="{{ old('price_per_night_usd', $stay->price_per_night_usd) }}" autocomplete="off" data-reference-price-usd @disabled($stay->status !== 'occupied')>
                                <div class="invalid-feedback">{{ $errors->first('price_per_night_usd') }}</div>
                            </div>
                            <input name="exchange_rate" type="hidden" value="{{ old('exchange_rate', $currentExchangeRate?->rate ?? $stay->exchange_rate) }}" data-reference-exchange-rate>
                            <div>
                                <input type="hidden" name="breakfast_included" value="0">
                                <label class="form-label d-none d-lg-block">&nbsp;</label>
                                <label class="check-in-switch check-in-switch-sm">
                                    <input class="form-check-input" name="breakfast_included" type="checkbox" value="1" @checked((bool) old('breakfast_included', $stay->breakfast_included)) @disabled($stay->status !== 'occupied')>
                                    <span>Desayuno</span>
                                </label>
                            </div>
                            <div class="check-in-edit-notes-field">
                                <label class="form-label">Notas</label>
                                <input class="form-control form-control-sm" name="notes" value="{{ old('notes', $stay->notes) }}" autocomplete="off" @disabled($stay->status !== 'occupied')>
                            </div>
                            <div class="check-in-edit-guests-field" data-stay-guests-section>
                                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                    <div>
                                        <label class="form-label mb-0">Acompanantes</label>
                                        <div class="form-hint">El titular ya cuenta como huesped.</div>
                                    </div>
                                    <button class="btn btn-outline-primary btn-sm" type="button" data-stay-add-guest @disabled($stay->status !== 'occupied')>
                                        <i class="ti ti-user-plus me-1"></i>Agregar
                                    </button>
                                </div>
                                @error('guests')
                                    <div class="alert alert-danger py-2">{{ $message }}</div>
                                @enderror
                                <div class="table-responsive {{ $hasAdditionalGuests ? '' : 'd-none' }}" data-stay-guests-table-wrap>
                                    <table class="table table-sm table-vcenter mb-1">
                                        <thead>
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Documento</th>
                                                <th>Nacimiento</th>
                                                <th>Pais</th>
                                                <th class="text-end">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody data-stay-guests-table>
                                            <tr data-stay-guests-empty>
                                                <td class="text-center text-secondary py-3" colspan="5">Sin acompanantes registrados.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-none" data-stay-guests>
                                    @foreach ($additionalGuests as $guestIndex => $guest)
                                        @include('check-ins.partials.stay-guest-row', [
                                            'stayIndex' => $stay->id,
                                            'guestIndex' => $guestIndex,
                                            'guest' => $guest,
                                            'countries' => $countries,
                                            'documentTypes' => $documentTypes,
                                            'defaultBirthCountry' => $stay->holderGuest?->birthCountry,
                                            'fieldPrefix' => 'guests',
                                            'errorPrefixBase' => 'guests',
                                        ])
                                    @endforeach
                                </div>
                                <div class="form-hint" data-stay-guest-count></div>
                                <template data-stay-guest-template>
                                    @include('check-ins.partials.stay-guest-row', [
                                        'stayIndex' => $stay->id,
                                        'guestIndex' => '__GUEST_INDEX__',
                                        'guest' => [],
                                        'countries' => $countries,
                                        'documentTypes' => $documentTypes,
                                        'defaultBirthCountry' => $stay->holderGuest?->birthCountry,
                                        'fieldPrefix' => 'guests',
                                        'errorPrefixBase' => 'guests',
                                    ])
                                </template>
                                <div class="modal fade" tabindex="-1" aria-hidden="true" data-stay-guest-modal>
                                    <div class="modal-dialog modal-lg modal-dialog-centered">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" data-stay-guest-modal-title>Acompanante</h5>
                                                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row g-3" data-stay-guest-row>
                                                    <input type="hidden" data-stay-guest-id>
                                                    <input type="hidden" data-stay-guest-edit-index>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Tipo documento</label>
                                                        <select class="form-select" data-stay-guest-document-type>
                                                            @foreach ($documentTypes as $value => $label)
                                                                <option value="{{ $value }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Documento</label>
                                                        <input class="form-control" autocomplete="off" data-stay-guest-document-number>
                                                        <div class="form-hint d-none" data-stay-guest-lookup-message></div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Nombre</label>
                                                        <input class="form-control" autocomplete="off" data-stay-guest-first-name>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label">Apellido paterno</label>
                                                        <input class="form-control" autocomplete="off" data-stay-guest-last-name>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label">Nacimiento</label>
                                                        <input class="form-control" type="date" max="{{ today()->toDateString() }}" autocomplete="off" data-stay-guest-birth-date>
                                                    </div>
                                                    <div class="col-md-8">
                                                        <label class="form-label">Pais nacimiento</label>
                                                        <select class="form-select" data-stay-guest-country data-countries-url="{{ route('countries.autocomplete') }}" data-placeholder="Buscar pais"></select>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                                                <button class="btn btn-primary" type="button" data-stay-guest-modal-save>
                                                    <i class="ti ti-check me-1"></i>Guardar acompanante
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="align-self-end">
                                <button class="btn btn-primary btn-sm w-100" type="submit" @disabled($stay->status !== 'occupied')>
                                    <i class="ti ti-refresh me-1"></i>Actualizar
                                </button>
                            </div>
                        </form>

                        <div class="check-in-edit-night-summary">
                            <div>
                                <span class="text-body-secondary small">Activas</span>
                                <div class="check-in-edit-badges">
                                    @forelse ($activeItems as $item)
                                        <span class="badge text-bg-light">{{ $item->date->toDateString() }} · {{ $item->total }} {{ $item->currency }}</span>
                                    @empty
                                        <span class="text-body-secondary small">Sin cargos activos.</span>
                                    @endforelse
                                </div>
                            </div>
                            <div>
                                <span class="text-body-secondary small">Canceladas</span>
                                <div class="check-in-edit-badges">
                                    @forelse ($cancelledItems as $item)
                                        <span class="badge text-bg-warning">{{ $item->date->toDateString() }}</span>
                                    @empty
                                        <span class="text-body-secondary small">Sin cargos cancelados.</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    </div>
@endsection
