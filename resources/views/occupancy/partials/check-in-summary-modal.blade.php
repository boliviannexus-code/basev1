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
    $countedStays = $group->status === 'checked_in'
        ? $relatedStays->where('status', 'occupied')
        : $relatedStays->where('status', '!=', 'cancelled');
    $stayCount = $countedStays
        ->unique(fn ($item) => $item->space_id.'|'.$item->space_room_id.'|'.$item->room_bed_unit_id)
        ->count();
    $groupStatements = $relatedStays->map(fn ($item) => $item->accountStatement)->filter();
    $groupBalance = (float) $groupStatements->sum('balance');
    $statementTotal = fn ($account) => $account
        ? ((float) $account->subtotal + (float) $account->extra_charges_total - (float) $account->discount_total)
        : 0;
    $groupTotal = (float) $groupStatements->sum(fn ($account) => $statementTotal($account));
    $money = fn ($value) => number_format((float) $value, 2).' '.company_currency_symbol();
    $statusLabels = [
        'checked_in' => 'En curso',
        'checked_out' => 'Finalizado',
        'cancelled' => 'Cancelado',
        'no_show' => 'No se presentó',
    ];
@endphp

<div class="check-in-read-panel check-in-summary-panel">
    <div class="check-in-read-header">
        <div class="d-flex flex-wrap align-items-center gap-3 gap-md-5">
            <h2 class="check-in-read-title fs-1 fw-bold text-break mb-0">{{ $holderName }}</h2>
            <div class="fw-semibold text-break">{{ $resourceLabel }}</div>
        </div>
        <span class="badge bg-primary-lt check-in-read-status">{{ $statusLabels[$group->status] ?? $group->status }}</span>
    </div>

    <div class="check-in-read-stats">
        <div>
            <div class="check-in-read-stat-line"><span>Ingreso</span><strong>{{ $group->check_in_date->format('d/m/Y') }}</strong></div>
            <div class="check-in-read-stat-line"><span>Salida grupo</span><strong>{{ $group->check_out_date->format('d/m/Y') }}</strong></div>
        </div>
        <div>
            <div class="check-in-read-stat-line"><span>{{ $group->status === 'checked_in' ? 'Estancias activas' : 'Estancias' }}</span><strong>{{ $stayCount }}</strong></div>
            <div class="check-in-read-stat-line"><span>Personas grupo</span><strong>{{ $group->total_people }}</strong></div>
        </div>
        <div class="check-in-read-balance {{ $groupBalance > 0 ? 'is-pending' : '' }}">
            <span>Saldo grupo</span>
            <strong><x-ui.money :amount="$groupBalance" :exchange-rate="$stay->exchange_rate" /></strong>
            <span @class(['text-danger' => $groupBalance > 0])>{{ $groupBalance > 0 ? 'Pendiente de pago' : 'Sin saldo pendiente' }}</span>
            @if ($groupBalance > 0)
                <button
                    class="btn btn-success btn-sm justify-self-start mt-2"
                    type="button"
                    data-modal-url="{{ route('stays.payments.create', ['stay' => $stay, 'scope' => 'group']) }}"
                    data-modal-title="Cobrar saldo del grupo"
                >
                    <i class="ti ti-cash-register me-1"></i>Cobrar
                </button>
            @endif
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
                    <div><dt>Precio noche</dt><dd><x-ui.money :amount="$stay->price_per_night_bob" :exchange-rate="$stay->exchange_rate" :inline="true" /></dd></div>
                    <div><dt>Hospedaje</dt><dd><x-ui.money :amount="$lodgingTotal" :exchange-rate="$stay->exchange_rate" :inline="true" /></dd></div>
                    <div><dt>Cargos extras</dt><dd><x-ui.money :amount="$extrasTotal" :exchange-rate="$stay->exchange_rate" :inline="true" /></dd></div>
                    <div><dt>Saldo estancia</dt><dd><x-ui.money :amount="$statement?->balance ?? 0" :exchange-rate="$stay->exchange_rate" :inline="true" /></dd></div>
                    <div><dt>Desayuno</dt><dd>{{ $stay->breakfast_included ? 'Incluido' : 'No incluido' }}</dd></div>
                </dl>
            </div>
        </div>
    </div>

    <div class="check-in-read-section mt-3">
        <div class="check-in-read-section-title">
            <i class="ti ti-cash"></i>
            <span>Resumen de cuenta</span>
        </div>
        <dl class="check-in-read-dl check-in-read-dl-compact">
            <div><dt>Total grupo</dt><dd><x-ui.money :amount="$groupTotal" :exchange-rate="$stay->exchange_rate" /></dd></div>
            <div><dt>Saldo grupo</dt><dd><x-ui.money :amount="$groupBalance" :exchange-rate="$stay->exchange_rate" /></dd></div>
            <div><dt>Total estancia seleccionada</dt><dd><x-ui.money :amount="$statementTotal($statement)" :exchange-rate="$stay->exchange_rate" /></dd></div>
            <div><dt>Saldo estancia seleccionada</dt><dd><x-ui.money :amount="$statement?->balance ?? 0" :exchange-rate="$stay->exchange_rate" /></dd></div>
        </dl>
    </div>

    <div class="check-in-read-section mt-3">
        <div class="check-in-read-section-title">
            <i class="ti ti-receipt"></i>
            <span>Cargos extras activos</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0 reservation-mobile-table">
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
                            <td data-label="Fecha">{{ $item->date?->format('d/m/Y') ?: '-' }}</td>
                            <td data-label="Categoría">{{ $item->extraChargeCategory?->name ?: '-' }}</td>
                            <td data-label="Detalle">{{ $item->description }}</td>
                            <td data-label="Cantidad" class="text-end">{{ number_format((float) $item->quantity, 2) }}</td>
                            <td data-label="Total" class="text-end fw-semibold"><x-ui.money class="align-items-end" :amount="$item->total" :currency="$item->currency" :exchange-rate="$stay->exchange_rate" /></td>
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
                        <strong><x-ui.money :amount="$relatedStay->accountStatement?->balance ?? 0" :exchange-rate="$relatedStay->exchange_rate" /></strong>
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
            <table class="table table-sm align-middle mb-0 reservation-mobile-table">
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
                            <td data-label="Nombre">{{ $guest->full_name }}</td>
                            <td data-label="Documento">{{ strtoupper((string) $guest->document_type) }} {{ $guest->document_number ?: '-' }}</td>
                            <td data-label="Nacimiento">{{ $guest->birth_date?->format('d/m/Y') ?: '-' }}{{ $guest->age ? ' · '.$guest->age.' años' : '' }}</td>
                            <td data-label="País">{{ $guest->birthCountry?->name ?: '-' }}</td>
                            <td data-label="Tipo" class="text-end">
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

    @if (auth()->user()?->hasRole('super_admin'))
        <form method="POST" action="{{ route('check-ins.destroy', $group) }}" class="mt-3"
              data-confirm-submit="¿Eliminar el check-in completo?"
              data-confirm-text="Se eliminarán todas las estancias de este check-in y se anularán sus cobros. Las reservas y sus adelantos se mantienen. Esta acción afecta los informes y las cajas, incluso las cerradas; no realiza una devolución de dinero."
              data-confirm-button="Eliminar check-in" data-confirm-color="#dc3545" data-confirm-icon="warning">
            @csrf
            @method('DELETE')
            <button class="btn btn-outline-danger btn-sm" type="submit"><i class="ti ti-trash me-1"></i>Eliminar check-in completo</button>
        </form>
    @endif

    @if ($stay->status === 'occupied' || (float) ($statement?->balance ?? 0) > 0)
        <div class="d-flex flex-wrap justify-content-end gap-2 mt-3 check-in-summary-actions">
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
