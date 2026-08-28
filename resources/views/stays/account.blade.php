@extends('layouts.admin')

@section('title', 'Cuenta y cobros | '.config('app.name', 'Base Admin'))
@section('page-title', 'Cuenta y cobros')
@section('page-subtitle', 'Consulta cargos, registra consumos y cobra la estancia '.$stay->checkInGroup?->code)

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
        $exchangeRate = (float) $stay->exchange_rate;
        $status = [
            'paid' => ['label' => 'Pagado', 'tone' => 'success'],
            'partial' => ['label' => 'Pago parcial', 'tone' => 'warning'],
            'open' => ['label' => 'Pendiente', 'tone' => 'secondary'],
        ][$statement->status] ?? ['label' => ucfirst($statement->status), 'tone' => 'secondary'];
    @endphp

    <x-ui.card class="mb-3">
        <div class="card-body">
            <div class="d-flex flex-column flex-lg-row gap-3 justify-content-between align-items-lg-center">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <span class="text-secondary">Saldo pendiente</span>
                        <span class="badge bg-{{ $status['tone'] }}-lt">{{ $status['label'] }}</span>
                    </div>
                    <div class="fs-1 fw-bold lh-1"><x-ui.money :amount="$statement->balance" :currency="$statement->currency" :exchange-rate="$exchangeRate" /></div>
                    <div class="text-secondary small mt-2">{{ $holderName }} · {{ $resourceLabel ?: 'Hospedaje' }}</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button
                        class="btn btn-outline-primary"
                        type="button"
                        data-modal-url="{{ route('stays.extra-charges.create', $stay) }}"
                        data-modal-title="Agregar cargo extra"
                    >
                        <i class="ti ti-plus me-1"></i>Agregar cargo
                    </button>
                    @if ((float) $statement->balance > 0 && $openRegister)
                        <button
                            class="btn btn-success"
                            type="button"
                            data-modal-url="{{ route('stays.payments.create', $stay) }}"
                            data-modal-title="Cobrar estancia"
                        >
                            <i class="ti ti-cash-register me-1"></i>Cobrar saldo
                        </button>
                    @elseif ((float) $statement->balance > 0)
                        <a class="btn btn-outline-success" href="{{ route('space-cash.index') }}">
                            <i class="ti ti-cash-register me-1"></i>Iniciar caja para cobrar
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </x-ui.card>

    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <div class="btn-list">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('occupancy.index') }}">
                <i class="ti ti-grid-dots me-1"></i>Grilla
            </a>
            <a class="btn btn-outline-primary btn-sm" href="{{ route('check-ins.edit', ['checkInGroup' => $stay->check_in_group_id, 'highlight_stay' => $stay->id]) }}#stay-{{ $stay->id }}">
                <i class="ti ti-edit me-1"></i>Editar estancia
            </a>
        </div>
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
                                    <td class="text-end"><x-ui.money class="align-items-end" :amount="$item->unit_price" :currency="$item->currency" :exchange-rate="$exchangeRate" /></td>
                                    <td class="text-end fw-semibold"><x-ui.money class="align-items-end" :amount="$item->total" :currency="$item->currency" :exchange-rate="$exchangeRate" /></td>
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
                                    <td class="text-end"><x-ui.money class="align-items-end" :amount="$item->unit_price" :currency="$item->currency" :exchange-rate="$exchangeRate" /></td>
                                    <td class="text-end fw-semibold"><x-ui.money class="align-items-end" :amount="$item->total" :currency="$item->currency" :exchange-rate="$exchangeRate" /></td>
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
                        <strong><x-ui.money class="align-items-end" :amount="$statement->subtotal" :currency="$statement->currency" :exchange-rate="$exchangeRate" /></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Extras</span>
                        <strong><x-ui.money class="align-items-end" :amount="$statement->extra_charges_total" :currency="$statement->currency" :exchange-rate="$exchangeRate" /></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Descuentos</span>
                        <strong><x-ui.money class="align-items-end" :amount="$statement->discount_total" :currency="$statement->currency" :exchange-rate="$exchangeRate" /></strong>
                    </div>
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span>Pagado</span>
                        <strong><x-ui.money class="align-items-end" :amount="$statement->payments_total" :currency="$statement->currency" :exchange-rate="$exchangeRate" /></strong>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-3">
                        <span class="fw-semibold">Saldo</span>
                        <strong class="fs-2"><x-ui.money class="align-items-end" :amount="$statement->balance" :currency="$statement->currency" :exchange-rate="$exchangeRate" /></strong>
                    </div>

                    @if (auth()->user()?->hasRole('super_admin'))
                        <form class="border-top mt-3 pt-3" method="POST" action="{{ route('stays.discounts.store', $stay) }}" autocomplete="off" novalidate>
                            @csrf
                            <div class="row g-2">
                                <div class="col-7">
                                    <label class="form-label" for="discount_amount">Descuento fijo</label>
                                    <input
                                        class="form-control text-end @error('amount', 'stayDiscount') is-invalid @enderror"
                                        id="discount_amount"
                                        name="amount"
                                        type="number"
                                        min="0.01"
                                        max="{{ number_format(max((float) $statement->subtotal - (float) $statement->discount_total, 0), 2, '.', '') }}"
                                        step="0.01"
                                        value="{{ old('amount') }}"
                                        required
                                    >
                                    @error('amount', 'stayDiscount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-5 d-flex align-items-end">
                                    <button class="btn btn-outline-warning w-100" type="submit">
                                        <i class="ti ti-discount-2 me-1"></i>Aplicar
                                    </button>
                                </div>
                                <div class="col-12">
                                    <input
                                        class="form-control form-control-sm @error('description', 'stayDiscount') is-invalid @enderror"
                                        name="description"
                                        type="text"
                                        maxlength="255"
                                        value="{{ old('description') }}"
                                        placeholder="Motivo del descuento"
                                    >
                                    @error('description', 'stayDiscount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </form>
                    @endif
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
                                <strong><x-ui.money class="align-items-end" :amount="$item->total" :currency="$item->currency" :exchange-rate="$exchangeRate" /></strong>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection
