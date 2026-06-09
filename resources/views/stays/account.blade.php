@extends('layouts.admin')

@section('title', 'Estado de cuenta | '.config('app.name', 'Base Admin'))
@section('page-title', 'Estado de cuenta')
@section('page-subtitle', $stay->checkInGroup?->code.' - Estancia #'.$stay->id)

@section('content')
    @php
        $resourceLabel = collect([
            $stay->space?->title ?: $stay->space?->name,
            $stay->room?->name ?: $stay->room?->title,
            $stay->bedUnit?->label,
        ])->filter()->implode(' / ');
        $holderName = trim(($stay->holderGuest?->first_name ?? '').' '.($stay->holderGuest?->last_name ?? '')) ?: 'Sin titular';
        $activeItems = $statement->items->where('status', 'active')->sortBy('date');
        $lodgingItems = $activeItems->where('type', 'lodging_night');
        $extraItems = $activeItems->whereIn('type', ['breakfast', 'extra', 'adjustment']);
        $manualExtraItems = $activeItems->where('type', 'extra');
        $discountItems = $activeItems->where('type', 'discount');
        $paymentItems = $activeItems->where('type', 'payment');
        $cancelledItems = $statement->items->where('status', 'cancelled')->sortBy('date');
        $money = fn ($value, $currency = null) => number_format((float) $value, 2).' '.($currency ?: $statement->currency);
    @endphp

    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <div class="btn-list">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('occupancy.index') }}">
                <i class="ti ti-grid-dots me-1"></i>Grilla
            </a>
            <a class="btn btn-outline-primary btn-sm" href="{{ route('check-ins.edit', ['checkInGroup' => $stay->check_in_group_id, 'highlight_stay' => $stay->id]) }}#stay-{{ $stay->id }}">
                <i class="ti ti-edit me-1"></i>Editar estancia
            </a>
            @if ((float) $statement->balance > 0 && $openRegister)
                <button
                    class="btn btn-success btn-sm"
                    type="button"
                    data-modal-url="{{ route('stays.payments.create', $stay) }}"
                    data-modal-title="Cobrar estancia"
                >
                    <i class="ti ti-cash-register me-1"></i>Cobrar
                </button>
            @elseif ((float) $statement->balance > 0)
                <a class="btn btn-outline-success btn-sm" href="{{ route('space-cash.index') }}">
                    <i class="ti ti-cash-register me-1"></i>Iniciar caja de espacios
                </a>
            @endif
        </div>
        <span class="badge bg-{{ $statement->status === 'paid' ? 'success' : ($statement->status === 'partial' ? 'warning' : 'secondary') }}-lt">
            {{ ucfirst($statement->status) }}
        </span>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <x-ui.card>
                <div class="card-header">
                    <h3 class="card-title">Estancia</h3>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="text-secondary small">Titular</div>
                            <div class="fw-semibold">{{ $holderName }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="text-secondary small">Recurso</div>
                            <div class="fw-semibold">{{ $resourceLabel ?: 'Hospedaje' }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-secondary small">Ingreso</div>
                            <div>{{ $stay->check_in_date->format('d/m/Y') }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-secondary small">Salida</div>
                            <div>{{ $stay->check_out_date->format('d/m/Y') }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-secondary small">Noches</div>
                            <div>{{ $stay->nights }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-secondary small">Personas</div>
                            <div>{{ $stay->people_count }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-secondary small">Precio BOB</div>
                            <div>{{ $money($stay->price_per_night_bob, 'BOB') }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-secondary small">Precio USD</div>
                            <div>{{ $money($stay->price_per_night_usd, 'USD') }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-secondary small">Moneda principal</div>
                            <div>{{ $stay->currency }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-secondary small">Tipo de cambio</div>
                            <div>{{ number_format((float) $stay->exchange_rate, 4) }}</div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-secondary small">Desayuno</div>
                            <div>{{ $stay->breakfast_included ? 'Con desayuno' : 'Sin desayuno' }}</div>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card class="mt-3">
                <div class="card-header">
                    <h3 class="card-title">Huespedes registrados</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Documento</th>
                                <th>Nacimiento</th>
                                <th>Edad</th>
                                <th>Pais</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($stay->guests->sortByDesc(fn ($guest) => (bool) $guest->pivot?->is_holder) as $guest)
                                <tr>
                                    <td>{{ $guest->full_name }}</td>
                                    <td>{{ strtoupper((string) $guest->document_type) }} {{ $guest->document_number ?: '-' }}</td>
                                    <td>{{ $guest->birth_date?->format('d/m/Y') ?: '-' }}</td>
                                    <td>{{ $guest->age ?? '-' }}</td>
                                    <td>{{ $guest->birthCountry?->name ?: '-' }}</td>
                                    <td class="text-end">
                                        @if ($guest->pivot?->is_holder)
                                            <span class="badge text-bg-primary">Titular</span>
                                        @else
                                            <span class="badge text-bg-light">Registro</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-center text-secondary py-4" colspan="6">No hay huespedes registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card class="mt-3">
                <div class="card-header">
                    <h3 class="card-title">Items diarios de hospedaje</h3>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Descripcion</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Unitario</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($lodgingItems as $item)
                                <tr>
                                    <td>{{ $item->date->format('d/m/Y') }}</td>
                                    <td>{{ $item->description }}</td>
                                    <td class="text-end">{{ number_format((float) $item->quantity, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $item->unit_price, 2) }} Bs</td>
                                    <td class="text-end fw-semibold">{{ number_format((float) $item->total, 2) }} Bs</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-center text-secondary py-4" colspan="5">No hay cargos activos de hospedaje.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            <x-ui.card class="mt-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Cargos extras</h3>
                    <button
                        class="btn btn-primary btn-sm"
                        type="button"
                        data-modal-url="{{ route('stays.extra-charges.create', $stay) }}"
                        data-modal-title="Agregar cargo extra"
                    >
                        <i class="ti ti-plus me-1"></i>Agregar cargo
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-vcenter card-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Categoria</th>
                                <th>Detalle</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Unitario</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($manualExtraItems as $item)
                                <tr>
                                    <td>{{ $item->date?->format('d/m/Y') ?: '-' }}</td>
                                    <td>{{ $item->extraChargeCategory?->name ?: '-' }}</td>
                                    <td>{{ $item->description }}</td>
                                    <td class="text-end">{{ number_format((float) $item->quantity, 2) }}</td>
                                    <td class="text-end">{{ $money($item->unit_price, $item->currency) }}</td>
                                    <td class="text-end fw-semibold">{{ $money($item->total, $item->currency) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="text-center text-secondary py-4" colspan="6">No hay cargos extras registrados.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>
        </div>

        <div class="col-lg-4">
            <x-ui.card>
                <div class="card-header">
                    <h3 class="card-title">Totales</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Subtotal</span>
                        <strong>{{ $money($statement->subtotal) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Extras</span>
                        <strong>{{ $money($statement->extra_charges_total) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Descuentos</span>
                        <strong>{{ $money($statement->discount_total) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Pagado</span>
                        <strong>{{ $money($statement->payments_total) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-3">
                        <span class="fw-semibold">Saldo</span>
                        <strong class="fs-2">{{ $money($statement->balance) }}</strong>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card class="mt-3">
                <div class="card-header">
                    <h3 class="card-title">Movimientos futuros</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Extras</span>
                        <strong>{{ $extraItems->count() }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Descuentos</span>
                        <strong>{{ $discountItems->count() }}</strong>
                    </div>
                    <div class="d-flex justify-content-between py-2">
                        <span>Pagos</span>
                        <strong>{{ $paymentItems->count() }}</strong>
                    </div>
                </div>
            </x-ui.card>

            @if ($cancelledItems->isNotEmpty())
                <x-ui.card class="mt-3">
                    <div class="card-header">
                        <h3 class="card-title">Items cancelados</h3>
                    </div>
                    <div class="card-body">
                        @foreach ($cancelledItems as $item)
                            <div class="d-flex justify-content-between gap-2 py-2 border-bottom">
                                <span>{{ $item->date?->format('d/m/Y') }} - {{ $item->description }}</span>
                                <strong>{{ $money($item->total, $item->currency) }}</strong>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection
