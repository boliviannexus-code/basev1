@php
    $holder = $stay->holderGuest ?: $group->mainGuest;
    $holderName = $holder?->full_name ?: 'Sin titular';
    $resourceLabel = collect([
        $stay->space?->title ?: $stay->space?->name,
        $stay->room?->name ?: $stay->room?->title,
        $stay->bedUnit?->label,
    ])->filter()->implode(' / ') ?: $resource_name;
    $activeItems = $statement?->items?->where('status', 'active') ?? collect();
    $extraItems = $activeItems->where('type', 'extra');
    $lodgingTotal = (float) $activeItems->where('type', 'lodging_night')->sum('total');
    $extrasTotal = (float) $extraItems->sum('total');
    $relatedStays = $group->stays->sortBy('id')->values();
    $groupStatements = $relatedStays->map(fn ($item) => $item->accountStatement)->filter();
    $groupBalance = (float) $groupStatements->sum('balance');
    $statementTotal = fn ($account) => $account
        ? ((float) $account->subtotal + (float) $account->extra_charges_total - (float) $account->discount_total)
        : 0;
    $groupTotal = (float) $groupStatements->sum(fn ($account) => $statementTotal($account));
    $selectedStayNumber = $relatedStays->search(fn ($item) => (int) $item->id === (int) $stay->id) + 1;
    $money = fn ($value) => number_format((float) $value, 2).' Bs';
    $statusLabels = [
        'checked_in' => 'En curso',
        'checked_out' => 'Finalizado',
        'cancelled' => 'Cancelado',
        'no_show' => 'No show',
    ];
@endphp

<div class="check-in-read-panel">
    <div class="check-in-read-header">
        <div>
            <div class="check-in-read-kicker">Vista de check-in</div>
            <div class="check-in-read-title">{{ $group->code }}</div>
            <div class="check-in-read-subtitle">{{ $holderName }} · Estancia {{ $selectedStayNumber }} de {{ $relatedStays->count() }}</div>
        </div>
        <span class="badge bg-primary-lt check-in-read-status">{{ $statusLabels[$group->status] ?? $group->status }}</span>
    </div>

    <div class="check-in-read-stats">
        <div>
            <span>Ingreso</span>
            <strong>{{ $group->check_in_date->format('d/m/Y') }}</strong>
        </div>
        <div>
            <span>Salida grupo</span>
            <strong>{{ $group->check_out_date->format('d/m/Y') }}</strong>
        </div>
        <div>
            <span>Estancias</span>
            <strong>{{ $relatedStays->count() }}</strong>
        </div>
        <div>
            <span>Personas grupo</span>
            <strong>{{ $group->total_people }}</strong>
        </div>
        <div>
            <span>Saldo grupo</span>
            <strong>{{ $money($groupBalance) }}</strong>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="check-in-read-section">
                <div class="check-in-read-section-title">
                    <i class="ti ti-user"></i>
                    <span>Titular</span>
                </div>
                <dl class="check-in-read-dl">
                    <div><dt>Nombre</dt><dd>{{ $holderName }}</dd></div>
                    <div><dt>Documento</dt><dd>{{ strtoupper((string) $holder?->document_type) }} {{ $holder?->document_number ?: '-' }}</dd></div>
                    <div><dt>Nacimiento</dt><dd>{{ $holder?->birth_date?->format('d/m/Y') ?: '-' }}{{ $holder?->age ? ' · '.$holder->age.' años' : '' }}</dd></div>
                    <div><dt>Pais</dt><dd>{{ $holder?->birthCountry?->name ?: '-' }}</dd></div>
                    <div><dt>Canal</dt><dd>{{ $group->reservationChannel?->name ?: 'Sin canal' }}</dd></div>
                </dl>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="check-in-read-section">
                <div class="check-in-read-section-title">
                    <i class="ti ti-home-dollar"></i>
                    <span>Estancia</span>
                </div>
                <dl class="check-in-read-dl">
                    <div><dt>Recurso</dt><dd>{{ $resourceLabel }}</dd></div>
                    <div><dt>Precio noche</dt><dd>{{ $money($stay->price_per_night_bob) }}</dd></div>
                    <div><dt>Hospedaje</dt><dd>{{ $money($lodgingTotal) }}</dd></div>
                    <div><dt>Cargos extras</dt><dd>{{ $money($extrasTotal) }}</dd></div>
                    <div><dt>Saldo estancia</dt><dd>{{ $money($statement?->balance ?? 0) }}</dd></div>
                    <div><dt>Desayuno</dt><dd>{{ $stay->breakfast_included ? 'Incluido' : 'No incluido' }}</dd></div>
                </dl>
            </div>
        </div>
    </div>

    <div class="check-in-read-section mt-3">
        <div class="check-in-read-section-title">
            <i class="ti ti-door"></i>
            <span>Estancias del check-in</span>
        </div>
        <div class="check-in-read-stay-list">
            @foreach ($relatedStays as $relatedStay)
                @php
                    $relatedResourceLabel = collect([
                        $relatedStay->space?->title ?: $relatedStay->space?->name,
                        $relatedStay->room?->name ?: $relatedStay->room?->title,
                        $relatedStay->bedUnit?->label,
                    ])->filter()->implode(' / ') ?: 'Recurso no asignado';
                    $relatedHolder = $relatedStay->holderGuest ?: $group->mainGuest;
                @endphp
                <div class="check-in-read-stay-item {{ (int) $relatedStay->id === (int) $stay->id ? 'is-selected' : '' }}">
                    <div>
                        <div class="fw-semibold">{{ $loop->iteration }}. {{ $relatedResourceLabel }}</div>
                        <div class="text-body-secondary small">{{ $relatedHolder?->full_name ?: 'Sin titular' }}</div>
                    </div>
                    <div class="check-in-read-stay-meta">
                        <span>{{ $relatedStay->check_in_date->format('d/m/Y') }} - {{ $relatedStay->check_out_date->format('d/m/Y') }}</span>
                        <span>{{ $relatedStay->people_count }} pers. · {{ $relatedStay->nights }} noches</span>
                        <strong>{{ $money($relatedStay->accountStatement?->balance ?? 0) }}</strong>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="check-in-read-section mt-3">
        <div class="check-in-read-section-title">
            <i class="ti ti-users"></i>
            <span>Huespedes de la estancia seleccionada</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Documento</th>
                        <th>Nacimiento</th>
                        <th>Pais</th>
                        <th class="text-end">Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stay->guests->sortByDesc(fn ($guest) => (bool) $guest->pivot?->is_holder) as $guest)
                        <tr>
                            <td>{{ $guest->full_name }}</td>
                            <td>{{ strtoupper((string) $guest->document_type) }} {{ $guest->document_number ?: '-' }}</td>
                            <td>{{ $guest->birth_date?->format('d/m/Y') ?: '-' }}{{ $guest->age ? ' · '.$guest->age.' años' : '' }}</td>
                            <td>{{ $guest->birthCountry?->name ?: '-' }}</td>
                            <td class="text-end">
                                <span class="badge text-bg-{{ $guest->pivot?->is_holder ? 'primary' : 'light' }}">
                                    {{ $guest->pivot?->is_holder ? 'Titular' : 'Acompañante' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-body-secondary py-3" colspan="5">No hay huespedes registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="check-in-read-section mt-3">
        <div class="check-in-read-section-title">
            <i class="ti ti-cash"></i>
            <span>Resumen de cuenta</span>
        </div>
        <dl class="check-in-read-dl check-in-read-dl-compact">
            <div><dt>Total grupo</dt><dd>{{ $money($groupTotal) }}</dd></div>
            <div><dt>Saldo grupo</dt><dd>{{ $money($groupBalance) }}</dd></div>
            <div><dt>Total estancia seleccionada</dt><dd>{{ $money($statementTotal($statement)) }}</dd></div>
            <div><dt>Saldo estancia seleccionada</dt><dd>{{ $money($statement?->balance ?? 0) }}</dd></div>
        </dl>
    </div>

    <div class="check-in-read-section mt-3">
        <div class="check-in-read-section-title">
            <i class="ti ti-receipt"></i>
            <span>Cargos extras activos</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Categoria</th>
                        <th>Detalle</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($extraItems as $item)
                        <tr>
                            <td>{{ $item->date?->format('d/m/Y') ?: '-' }}</td>
                            <td>{{ $item->extraChargeCategory?->name ?: '-' }}</td>
                            <td>{{ $item->description }}</td>
                            <td class="text-end">{{ number_format((float) $item->quantity, 2) }}</td>
                            <td class="text-end fw-semibold">{{ $money($item->total) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-body-secondary py-3" colspan="5">No hay cargos extras activos.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($group->notes || $stay->notes)
        <div class="check-in-read-section mt-3">
            <div class="check-in-read-section-title">
                <i class="ti ti-notes"></i>
                <span>Notas</span>
            </div>
            @if ($group->notes)
                <p class="mb-2"><strong>Check-in:</strong> {{ $group->notes }}</p>
            @endif
            @if ($stay->notes)
                <p class="mb-0"><strong>Estancia:</strong> {{ $stay->notes }}</p>
            @endif
        </div>
    @endif

    @if ($stay->status === 'occupied' || (float) ($statement?->balance ?? 0) > 0)
        <div class="d-flex justify-content-end gap-2 mt-3">
            @if ($stay->status === 'occupied')
                <button
                    class="btn btn-outline-primary btn-sm"
                    type="button"
                    data-modal-url="{{ route('stays.room-change.create', $stay) }}"
                    data-modal-title="Cambiar habitacion"
                >
                    <i class="ti ti-switch-horizontal me-1"></i>Cambiar habitacion
                </button>
            @endif
            @if ((float) ($statement?->balance ?? 0) > 0)
            <button
                class="btn btn-success btn-sm"
                type="button"
                data-modal-url="{{ route('stays.payments.create', $stay) }}"
                data-modal-title="Cobrar check-in"
            >
                <i class="ti ti-cash-register me-1"></i>Cobrar
            </button>
            @endif
        </div>
    @endif
</div>
