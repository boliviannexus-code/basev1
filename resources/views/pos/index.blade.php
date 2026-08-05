@extends('layouts.admin')

@section('title', 'Caja | Cobros')
@section('page-title', 'Caja')
@section('page-subtitle', 'Apertura y cierre de caja por usuario')

@section('content')
    @if ($openRegister || ($companyHasOpenRegister ?? false))
        @php
            $posCustomers = $customers->map(function ($customer): array {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'document_number' => $customer->document_number,
                    'sales_count' => $customer->sales_count,
                ];
            })->values();
            $cashPaymentMethod = $paymentMethods->firstWhere('name', 'Efectivo') ?? $paymentMethods->first();
        @endphp
        @unless ($openRegister)
            <div class="alert alert-info">
                No tienes una caja abierta en tu sesion. Puedes registrar ingresos o egresos usando el codigo de caja de un usuario que si tenga caja abierta.
            </div>
            @include('pos.partials.open-register-form', ['title' => 'Abrir mi caja', 'class' => 'mb-3'])
        @endunless

        <form
            class="pos-sale-form"
            method="POST"
            action="{{ route('pos.sales.store') }}"
            data-pos-sale-form
            data-pos-stock='@json($stockAvailability)'
            data-pos-customers='@json($posCustomers)'
            autocomplete="off"
            novalidate
        >
            @csrf

            <div class="pos-workspace">
                <section class="pos-main">
                    <div class="card pos-session-card mb-2">
                        <div class="card-body py-2">
                            <div class="pos-session-bar">
                                <div class="pos-session-item">
                                    <span>Empresa</span>
                                    <strong>{{ $openRegister->company?->name ?? auth()->user()?->company?->name }}</strong>
                                </div>
                                <div class="pos-session-item">
                                    <span>Usuario</span>
                                    <strong>{{ $openRegister?->user?->name ?? 'Segun codigo de caja' }}</strong>
                                </div>
                                <div class="pos-session-item">
                                    <span>Apertura</span>
                                    <strong>{{ $openRegister?->opened_at?->format('Y-m-d H:i') ?? '-' }}</strong>
                                </div>
                                <div class="pos-session-item">
                                    <span>Base caja</span>
                                    <strong>{{ money_format_decimal($openRegister?->opening_amount ?? 0) }}</strong>
                                </div>
                                <div class="btn-group pos-mode-switch" role="group" aria-label="Modo de venta">
                                    <button class="btn btn-primary btn-sm" type="button" data-pos-mode-toggle="normal">
                                        <i class="ti ti-list-search"></i>
                                        Normal
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm" type="button" data-pos-mode-toggle="quick">
                                        <i class="ti ti-layout-grid-add"></i>
                                        Agil
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-2 pos-entry-card" data-pos-mode-panel="normal">
                        <div class="card-header py-2">
                            <h3 class="card-title mb-0">Producto</h3>
                            <div class="card-actions">
                                <button class="btn btn-primary btn-sm" type="button" data-add-pos-item>
                                    <i class="ti ti-plus"></i>
                                    Agregar
                                </button>
                            </div>
                        </div>
                        <div class="card-body py-2">
                            <div class="alert alert-warning mb-3">
                                La venta de productos esta desactivada hasta habilitar almacenes e inventario. Esta caja puede usarse para cobros de hospedaje y egresos.
                            </div>
                            <div class="row g-3 align-items-end" data-pos-picker>
                                <div class="col-lg-5">
                                    <label class="form-label" for="pos-product-picker">Producto</label>
                                    <select class="form-select" id="pos-product-picker" data-tom-select data-pos-product-picker data-placeholder="Buscar producto">
                                        <option value="">Seleccionar</option>
                                        @foreach ($products as $product)
                                            @php
                                                $available = $stockAvailability[$product->id]['stock'] ?? 0;
                                                $unit = $product->measurementUnit?->abbreviation ?? 'u';
                                            @endphp
                                            <option
                                                value="{{ $product->id }}"
                                                data-name="{{ $product->name }}"
                                                data-price="{{ $product->sale_price }}"
                                                data-unit="{{ $unit }}"
                                                data-stock="{{ $available }}"
                                            >
                                                {{ $product->name }} - {{ $available }} {{ $unit }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3">
                                    <label class="form-label" for="pos-presentation-picker">Presentacion</label>
                                    <select class="form-select" id="pos-presentation-picker" data-tom-select data-pos-presentation-picker data-placeholder="Presentacion">
                                        <option value="">Selecciona producto</option>
                                    </select>
                                </div>
                                <div class="col-6 col-lg-2">
                                    <label class="form-label" for="pos-quantity-picker">Cantidad</label>
                                    <input class="form-control text-end" id="pos-quantity-picker" type="number" min="1" step="1" value="1" data-pos-quantity-picker>
                                </div>
                                <div class="col-6 col-lg-2">
                                    <button class="btn btn-primary w-100" type="button" data-add-pos-item>
                                        <i class="ti ti-shopping-cart-plus"></i>
                                        Agregar
                                    </button>
                                </div>
                            </div>
                            @error('items')<div class="text-danger small mt-3">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="card mb-2 d-none pos-entry-card" data-pos-mode-panel="quick">
                        <div class="card-header py-2">
                            <h3 class="card-title mb-0">Venta agil</h3>
                        </div>
                        <div class="card-body py-2">
                            @if (! $quickUnitPresentation)
                                <div class="alert alert-warning mb-0">
                                    Crea una presentacion activa de 1 unidad para habilitar la venta agil.
                                </div>
                            @elseif ($quickSaleCategories->isEmpty())
                                <div class="alert alert-warning mb-0">
                                    No hay productos activos categorizados para venta agil.
                                </div>
                            @else
                                <ul class="nav nav-tabs pos-category-tabs" role="tablist">
                                    @foreach ($quickSaleCategories as $category)
                                        <li class="nav-item" role="presentation">
                                            <button
                                                class="nav-link @if ($loop->first) active @endif"
                                                id="quick-category-{{ $category->id }}-tab"
                                                data-bs-toggle="tab"
                                                data-bs-target="#quick-category-{{ $category->id }}"
                                                type="button"
                                                role="tab"
                                                aria-controls="quick-category-{{ $category->id }}"
                                                aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                            >
                                                {{ $category->name }}
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>

                                <div class="tab-content pt-2">
                                    @foreach ($quickSaleCategories as $category)
                                        <div
                                            class="tab-pane fade @if ($loop->first) show active @endif"
                                            id="quick-category-{{ $category->id }}"
                                            role="tabpanel"
                                            aria-labelledby="quick-category-{{ $category->id }}-tab"
                                        >
                                            <div class="pos-quick-grid">
                                                @foreach ($category->products as $product)
                                                    @php
                                                        $unitAvailability = collect($stockAvailability[$product->id]['presentations'] ?? [])->firstWhere('id', $quickUnitPresentation->id);
                                                        $unitStock = (int) ($unitAvailability['packages'] ?? 0);
                                                        $unitLabel = $product->measurementUnit?->abbreviation ?? 'u';
                                                        $enabled = $unitStock > 0;
                                                        $words = preg_split('/\s+/', trim($product->name)) ?: [];
                                                        $initials = collect($words)->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
                                                        $initials = $initials !== '' ? $initials : 'P';
                                                    @endphp
                                                    <button
                                                        class="pos-product-tile {{ $enabled ? '' : 'is-disabled' }}"
                                                        type="button"
                                                        data-pos-quick-product
                                                        data-product-id="{{ $product->id }}"
                                                        data-presentation-id="{{ $quickUnitPresentation->id }}"
                                                        data-product-name="{{ $product->name }}"
                                                        data-presentation-name="{{ $quickUnitPresentation->name }}"
                                                        data-price="{{ $product->sale_price }}"
                                                        data-unit="{{ $unitLabel }}"
                                                        data-stock="{{ $unitStock }}"
                                                        @disabled(! $enabled)
                                                    >
                                                        <span class="pos-product-image">
                                                            @if ($product->image_url)
                                                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}">
                                                            @else
                                                                <span class="pos-product-initials">{{ $initials }}</span>
                                                            @endif
                                                            @if (! $enabled)
                                                                <span class="pos-product-overlay">Sin stock</span>
                                                            @endif
                                                        </span>
                                                        <span class="pos-product-name">{{ $product->name }}</span>
                                                        <span class="pos-product-meta">
                                                            {{ money_format_decimal($product->sale_price) }} · {{ $unitStock }} {{ $unitLabel }}
                                                        </span>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="card pos-cart-card">
                        <div class="table-responsive">
                            <table class="table table-vcenter pos-lines-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Presentacion</th>
                                        <th class="text-end">Cant.</th>
                                        <th class="text-end">Precio</th>
                                        <th class="text-end">Desc.</th>
                                        <th class="text-end">Subtotal</th>
                                        <th class="text-end"></th>
                                    </tr>
                                </thead>
                                <tbody data-pos-items></tbody>
                            </table>
                        </div>
                        <div class="card-body py-3 text-center text-body-secondary" data-pos-empty>
                            Agrega productos para iniciar la venta.
                        </div>
                    </div>
                </section>

                <aside class="pos-checkout">
                    <div class="card">
                        <div class="card-header py-2">
                            <h3 class="card-title mb-0">Cobro</h3>
                            <div class="card-actions">
                                @if ($openRegister)
                                    <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#cashCloseModal">
                                        <i class="ti ti-lock"></i>
                                        Cerrar caja
                                    </button>
                                @endif
                                <button class="btn btn-outline-success btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#cashIncomeModal">
                                    <i class="ti ti-cash-plus"></i>
                                    Ingreso
                                </button>
                                <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#cashExpenseModal">
                                    <i class="ti ti-cash-banknote-off"></i>
                                    Egreso
                                </button>
                            </div>
                        </div>
                        <div class="card-body py-2">
                            @if (empty($stockAvailability))
                                <div class="alert alert-warning mb-3">
                                    El almacen vinculado a esta caja no tiene productos disponibles para vender.
                                </div>
                            @endif

                            <div class="mb-2">
                                <input type="hidden" id="customer_id" name="customer_id" value="{{ old('customer_id') }}" data-pos-customer-id>
                                <label class="form-label" for="customer_document_number">ID documento</label>
                                <input
                                    class="form-control @error('customer_document_number') is-invalid @enderror"
                                    id="customer_document_number"
                                    name="customer_document_number"
                                    type="text"
                                    value="{{ old('customer_document_number') }}"
                                    placeholder="Venta rapida sin cliente"
                                    autocomplete="off"
                                    data-lpignore="true"
                                    data-1p-ignore="true"
                                    data-pos-customer-document
                                >
                                @error('customer_document_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-2">
                                <label class="form-label" for="customer_name">Nombre</label>
                                <input
                                    class="form-control @error('customer_name') is-invalid @enderror"
                                    id="customer_name"
                                    name="customer_name"
                                    type="text"
                                    value="{{ old('customer_name') }}"
                                    placeholder="Consumidor final"
                                    data-pos-customer-name
                                    autocomplete="off"
                                >
                                @error('customer_name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                <div class="form-text" data-pos-customer-status>Sin cliente asociado.</div>
                            </div>

                            <div class="pos-total-row">
                                <span>Subtotal</span>
                                <strong data-pos-subtotal>0.00</strong>
                            </div>
                            <div class="pos-total-row">
                                <span>Descuento</span>
                                <strong data-pos-discount>0.00</strong>
                            </div>
                            <div class="pos-grand-total">
                                <span>Total</span>
                                <strong data-pos-total>0.00</strong>
                            </div>

                            <div class="mt-3" data-pos-payment-section>
                                <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                                    <label class="form-label mb-0">Pagos</label>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-primary" type="button" data-pos-use-cash>Efectivo</button>
                                        <button class="btn btn-outline-primary" type="button" data-pos-use-mixed @disabled($paymentMethods->isEmpty())>Otro/mixto</button>
                                    </div>
                                </div>

                                @if ($paymentMethods->isEmpty())
                                    <div class="alert alert-warning mb-0">No hay metodos de pago activos.</div>
                                @else
                                    <input type="hidden" name="payment_mode" value="cash" data-pos-payment-mode>
                                    <input type="hidden" name="cash_payment_method_id" value="{{ $cashPaymentMethod?->id }}" data-pos-cash-method>

                                    <div data-pos-cash-panel>
                                        <label class="form-label" for="pos-cash-received">Monto recibido</label>
                                        <input class="form-control form-control-lg text-end" id="pos-cash-received" name="cash_received" type="number" min="0" step="0.01" placeholder="0.00" data-pos-cash-received>
                                        @error('cash_received')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                                        <div class="pos-change-box mt-2">
                                            <span>Cambio</span>
                                            <strong data-pos-cash-change>0.00</strong>
                                        </div>
                                    </div>

                                    <div class="d-none" data-pos-mixed-panel>
                                        <div class="d-flex justify-content-end mb-2">
                                            <button class="btn btn-outline-primary btn-sm" type="button" data-add-pos-payment>
                                                <i class="ti ti-plus"></i>
                                                Agregar pago
                                            </button>
                                        </div>
                                        <div class="vstack gap-2" data-pos-payments>
                                        <div class="pos-payment-row" data-pos-payment-row>
                                            <select class="form-select form-select-sm" data-pos-payment-method>
                                                @foreach ($paymentMethods as $paymentMethod)
                                                    <option value="{{ $paymentMethod->id }}">{{ $paymentMethod->name }}</option>
                                                @endforeach
                                            </select>
                                            <input class="form-control form-control-sm text-end" type="number" min="0.01" step="0.01" data-pos-payment-amount>
                                            <input class="form-control form-control-sm" type="text" placeholder="Ref." data-pos-payment-reference>
                                            <button class="btn btn-outline-danger btn-icon btn-sm" type="button" data-remove-pos-payment title="Quitar pago">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </div>
                                        </div>
                                        <div class="d-flex justify-content-between small text-body-secondary mt-2">
                                            <span>Pagado: <strong data-pos-paid>0.00</strong></span>
                                            <span>Saldo: <strong data-pos-due>0.00</strong></span>
                                        </div>
                                        @error('payments')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                                    </div>
                                @endif
                            </div>

                            <div class="mt-2">
                                <label class="form-label" for="notes">Observaciones</label>
                                <textarea class="form-control form-control-sm pos-notes-field" id="notes" name="notes" rows="1"></textarea>
                            </div>
                        </div>
                        <div class="card-footer d-grid gap-2 py-2">
                            <button class="btn btn-success" type="submit" data-pos-submit disabled>
                                <i class="ti ti-cash"></i>
                                Venta desactivada
                            </button>
                        </div>
                    </div>
                </aside>
            </div>

            <template data-pos-line-template>
                <tr data-pos-row>
                    <td>
                        <input type="hidden" data-pos-product-input>
                        <div class="fw-semibold" data-pos-product-name></div>
                    </td>
                    <td>
                        <input type="hidden" data-pos-presentation-input>
                        <span class="fw-semibold" data-pos-presentation-name></span>
                        <div class="text-body-secondary small" data-pos-calculation></div>
                    </td>
                    <td class="pos-number-cell"><input class="form-control form-control-sm text-end" type="number" min="1" step="1" data-pos-line-quantity></td>
                    <td class="pos-money-cell"><input class="form-control form-control-sm text-end" type="number" min="0" step="0.01" data-pos-line-price></td>
                    <td class="pos-money-cell"><input class="form-control form-control-sm text-end" type="number" min="0" step="0.01" value="0" data-pos-line-discount></td>
                    <td class="text-end fw-semibold pos-subtotal-cell" data-pos-line-subtotal>0.00</td>
                    <td class="text-end">
                        <button class="btn btn-outline-danger btn-icon" type="button" data-pos-remove title="Quitar">
                            <i class="ti ti-trash"></i>
                        </button>
                    </td>
                </tr>
            </template>

            <template data-pos-payment-template>
                <div class="pos-payment-row" data-pos-payment-row>
                    <select class="form-select form-select-sm" data-pos-payment-method>
                        @foreach ($paymentMethods as $paymentMethod)
                            <option value="{{ $paymentMethod->id }}">{{ $paymentMethod->name }}</option>
                        @endforeach
                    </select>
                    <input class="form-control form-control-sm text-end" type="number" min="0.01" step="0.01" data-pos-payment-amount>
                    <input class="form-control form-control-sm" type="text" placeholder="Ref." data-pos-payment-reference>
                    <button class="btn btn-outline-danger btn-icon btn-sm" type="button" data-remove-pos-payment title="Quitar pago">
                        <i class="ti ti-trash"></i>
                    </button>
                </div>
            </template>
        </form>

        <div class="modal modal-blur fade" id="cashIncomeModal" tabindex="-1" aria-labelledby="cashIncomeModalTitle" aria-hidden="true" @if ($errors->cashIncome->any()) data-show-cash-income-modal @endif>
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" action="{{ route('pos.incomes.store') }}" autocomplete="off" novalidate data-ajax-form data-requires-transaction-pin>
                    @csrf

                    <div class="modal-header">
                        <h2 class="modal-title" id="cashIncomeModalTitle">Registrar ingreso</h2>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label" for="income_category">Categoria</label>
                            <select
                                class="form-select @error('extra_charge_category_id', 'cashIncome') is-invalid @enderror"
                                id="income_category"
                                name="extra_charge_category_id"
                                data-remote-category-select
                                data-url="{{ route('extra-charge-categories.autocomplete') }}"
                                data-placeholder="Buscar categoria"
                                required
                            >
                                <option value="">Seleccionar categoria</option>
                                @foreach ($expenseCategories as $category)
                                    <option value="{{ $category->id }}" data-default-unit-price="{{ $category->default_unit_price }}" @selected((int) old('extra_charge_category_id') === (int) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('extra_charge_category_id', 'cashIncome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="income_payment_method">Metodo de pago</label>
                            <select
                                class="form-select @error('payment_method_id', 'cashIncome') is-invalid @enderror"
                                id="income_payment_method"
                                name="payment_method_id"
                                required
                            >
                                <option value="">Seleccionar metodo</option>
                                @foreach ($paymentMethods as $paymentMethod)
                                    <option value="{{ $paymentMethod->id }}" @selected((int) old('payment_method_id') === (int) $paymentMethod->id)>{{ $paymentMethod->name }}</option>
                                @endforeach
                            </select>
                            @error('payment_method_id', 'cashIncome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="income_detail">Detalle</label>
                            <textarea
                                class="form-control @error('detail', 'cashIncome') is-invalid @enderror"
                                id="income_detail"
                                name="detail"
                                rows="3"
                                maxlength="255"
                            >{{ old('detail') }}</textarea>
                            @error('detail', 'cashIncome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="income_reference">Referencia</label>
                            <input
                                class="form-control @error('reference', 'cashIncome') is-invalid @enderror"
                                id="income_reference"
                                name="reference"
                                type="text"
                                value="{{ old('reference') }}"
                                maxlength="255"
                            >
                            @error('reference', 'cashIncome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="income_quantity">Cantidad</label>
                            <input
                                class="form-control text-end @error('quantity', 'cashIncome') is-invalid @enderror"
                                id="income_quantity"
                                name="quantity"
                                type="number"
                                min="0.5"
                                step="0.5"
                                value="{{ old('quantity', '1') }}"
                                required
                            >
                            @error('quantity', 'cashIncome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label class="form-label" for="income_amount">Monto</label>
                            <input
                                class="form-control text-end @error('amount', 'cashIncome') is-invalid @enderror"
                                id="income_amount"
                                name="amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                value="{{ old('amount') }}"
                                required
                            >
                            @error('amount', 'cashIncome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-link link-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-success" type="submit">
                            <i class="ti ti-check"></i>
                            Registrar ingreso
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal modal-blur fade" id="cashExpenseModal" tabindex="-1" aria-labelledby="cashExpenseModalTitle" aria-hidden="true" @if ($errors->cashExpense->any()) data-show-cash-expense-modal @endif>
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" method="POST" action="{{ route('pos.expenses.store') }}" autocomplete="off" novalidate data-ajax-form data-requires-transaction-pin>
                    @csrf

                    <div class="modal-header">
                        <h2 class="modal-title" id="cashExpenseModalTitle">Registrar egreso</h2>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            @if ($openRegister)
                                Disponible en efectivo: <strong>{{ money_format_decimal($cashSummary['available'] ?? 0) }}</strong>
                            @else
                                El efectivo disponible se validara con la caja del usuario dueño del codigo.
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="expense_responsible_name">Encargado</label>
                            <input
                                class="form-control @error('responsible_name', 'cashExpense') is-invalid @enderror"
                                id="expense_responsible_name"
                                name="responsible_name"
                                type="text"
                                value="{{ old('responsible_name', auth()->user()?->name) }}"
                                maxlength="255"
                                required
                            >
                            @error('responsible_name', 'cashExpense')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="expense_category">Categoria</label>
                            <select
                                class="form-select @error('extra_charge_category_id', 'cashExpense') is-invalid @enderror"
                                id="expense_category"
                                name="extra_charge_category_id"
                                data-remote-category-select
                                data-url="{{ route('extra-charge-categories.autocomplete') }}"
                                data-placeholder="Buscar categoria"
                                required
                            >
                                <option value="">Seleccionar categoria</option>
                                @foreach ($expenseCategories as $category)
                                    <option value="{{ $category->id }}" data-default-unit-price="{{ $category->default_unit_price }}" @selected((int) old('extra_charge_category_id') === (int) $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('extra_charge_category_id', 'cashExpense')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="expense_payment_method">Metodo de pago</label>
                            <select
                                class="form-select @error('payment_method_id', 'cashExpense') is-invalid @enderror"
                                id="expense_payment_method"
                                name="payment_method_id"
                                required
                            >
                                <option value="">Seleccionar metodo</option>
                                @foreach ($paymentMethods as $paymentMethod)
                                    <option value="{{ $paymentMethod->id }}" @selected((int) old('payment_method_id') === (int) $paymentMethod->id)>{{ $paymentMethod->name }}</option>
                                @endforeach
                            </select>
                            @error('payment_method_id', 'cashExpense')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="expense_detail">Detalle</label>
                            <textarea
                                class="form-control @error('detail', 'cashExpense') is-invalid @enderror"
                                id="expense_detail"
                                name="detail"
                                rows="3"
                                maxlength="1000"
                            >{{ old('detail') }}</textarea>
                            @error('detail', 'cashExpense')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="expense_quantity">Cantidad</label>
                            <input
                                class="form-control text-end @error('quantity', 'cashExpense') is-invalid @enderror"
                                id="expense_quantity"
                                name="quantity"
                                type="number"
                                min="0.5"
                                step="0.5"
                                value="{{ old('quantity', '1') }}"
                                required
                            >
                            @error('quantity', 'cashExpense')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div>
                            <label class="form-label" for="expense_amount">Monto</label>
                            <input
                                class="form-control text-end @error('amount', 'cashExpense') is-invalid @enderror"
                                id="expense_amount"
                                name="amount"
                                type="number"
                                min="0.01"
                                @if ($openRegister) max="{{ number_format((float) ($cashSummary['available'] ?? 0), 2, '.', '') }}" @endif
                                step="0.01"
                                value="{{ old('amount') }}"
                                required
                            >
                            @error('amount', 'cashExpense')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-link link-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-danger" type="submit">
                            <i class="ti ti-check"></i>
                            Registrar egreso
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if ($openRegister)
        <div class="modal modal-blur fade" id="cashCloseModal" tabindex="-1" aria-labelledby="cashCloseModalTitle" aria-hidden="true" @if ($errors->cashClose->any()) data-show-cash-close-modal @endif>
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <form class="modal-content" method="POST" action="{{ route('pos.close') }}" autocomplete="off" novalidate>
                    @csrf

                    <div class="modal-header">
                        <h2 class="modal-title" id="cashCloseModalTitle">Cierre de caja</h2>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-sm-6 col-lg">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-body-secondary small">Base inicial</div>
                                    <div class="h3 mb-0">{{ money_format_decimal($cashSummary['opening'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-body-secondary small">Ventas</div>
                                    <div class="h3 mb-0">{{ money_format_decimal($cashSummary['sales_total'] ?? 0) }}</div>
                                    <div class="text-body-secondary small">{{ $cashSummary['sales_count'] ?? 0 }} comprobante(s)</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-body-secondary small">Hospedaje</div>
                                    <div class="h3 mb-0">{{ money_format_decimal($cashSummary['lodging_total'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-body-secondary small">Egresos</div>
                                    <div class="h3 mb-0">{{ money_format_decimal($cashSummary['expenses'] ?? 0) }}</div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-body-secondary small">Efectivo esperado</div>
                                    <div class="h3 mb-0">{{ money_format_decimal($cashSummary['available'] ?? 0) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion mt-3" id="cashCloseDetails">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="cashClosePaymentsHeading">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cashClosePayments" aria-expanded="false" aria-controls="cashClosePayments">
                                        Resumen por metodo de pago
                                    </button>
                                </h2>
                                <div class="accordion-collapse collapse" id="cashClosePayments" aria-labelledby="cashClosePaymentsHeading" data-bs-parent="#cashCloseDetails">
                                    <div class="accordion-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-vcenter mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Metodo</th>
                                                        <th class="text-end">Pagos</th>
                                                        <th class="text-end">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse (($cashSummary['payments'] ?? []) as $payment)
                                                        <tr>
                                                            <td>{{ $payment['name'] }}</td>
                                                            <td class="text-end">{{ $payment['payments_count'] }}</td>
                                                            <td class="text-end fw-semibold">{{ money_format_decimal($payment['total']) }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td class="text-center text-body-secondary" colspan="3">Sin pagos registrados.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="cashCloseLodgingHeading">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cashCloseLodging" aria-expanded="false" aria-controls="cashCloseLodging">
                                        Detalle de hospedaje
                                    </button>
                                </h2>
                                <div class="accordion-collapse collapse" id="cashCloseLodging" aria-labelledby="cashCloseLodgingHeading" data-bs-parent="#cashCloseDetails">
                                    <div class="accordion-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-vcenter mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Comprobante</th>
                                                        <th>Estancia</th>
                                                        <th>Metodo</th>
                                                        <th class="text-end">Total BOB</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse (($cashSummary['lodging_payments'] ?? []) as $payment)
                                                        <tr>
                                                            <td class="fw-semibold">{{ $payment->receipt_number }}</td>
                                                            <td>{{ $payment->stay?->holderGuest?->full_name ?? 'Hospedaje' }}</td>
                                                            <td>{{ $payment->paymentMethod?->name ?? '-' }}</td>
                                                            <td class="text-end fw-semibold">{{ money_format_decimal($payment->amount_bob) }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td class="text-center text-body-secondary" colspan="4">Sin cobros de hospedaje.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="cashCloseSalesHeading">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cashCloseSales" aria-expanded="false" aria-controls="cashCloseSales">
                                        Detalle de ventas
                                    </button>
                                </h2>
                                <div class="accordion-collapse collapse" id="cashCloseSales" aria-labelledby="cashCloseSalesHeading" data-bs-parent="#cashCloseDetails">
                                    <div class="accordion-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-vcenter mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Comprobante</th>
                                                        <th>Fecha</th>
                                                        <th>Pagos</th>
                                                        <th class="text-end">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse (($cashSummary['sales'] ?? []) as $sale)
                                                        <tr>
                                                            <td class="fw-semibold">{{ $sale->receipt_number }}</td>
                                                            <td>{{ $sale->sale_date?->format('Y-m-d H:i') }}</td>
                                                            <td>
                                                                @foreach ($sale->payments as $payment)
                                                                    <span class="badge bg-blue-lt me-1">{{ $payment->payment_method_name }} {{ money_format_decimal($payment->amount) }}</span>
                                                                @endforeach
                                                            </td>
                                                            <td class="text-end fw-semibold">{{ money_format_decimal($sale->total) }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td class="text-center text-body-secondary" colspan="4">Sin ventas registradas.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="cashCloseExpensesHeading">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cashCloseExpenses" aria-expanded="false" aria-controls="cashCloseExpenses">
                                        Detalle de egresos
                                    </button>
                                </h2>
                                <div class="accordion-collapse collapse" id="cashCloseExpenses" aria-labelledby="cashCloseExpensesHeading" data-bs-parent="#cashCloseDetails">
                                    <div class="accordion-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-vcenter mb-0">
                                                <thead>
                                                    <tr>
                                                        <th>Fecha</th>
                                                        <th>Encargado</th>
                                                        <th>Categoria</th>
                                                        <th>Metodo</th>
                                                        <th>Detalle</th>
                                                        <th class="text-end">Monto</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @forelse (($cashSummary['expense_details'] ?? []) as $expense)
                                                        <tr>
                                                            <td>{{ $expense->spent_at?->format('Y-m-d H:i') }}</td>
                                                            <td>{{ $expense->responsible_name }}</td>
                                                            <td>{{ $expense->category?->name ?: '-' }}</td>
                                                            <td>{{ $expense->paymentMethod?->name ?: 'Efectivo' }}</td>
                                                            <td>{{ $expense->detail }}</td>
                                                            <td class="text-end fw-semibold">{{ money_format_decimal($expense->amount) }}</td>
                                                        </tr>
                                                    @empty
                                                        <tr>
                                                            <td class="text-center text-body-secondary" colspan="6">Sin egresos registrados.</td>
                                                        </tr>
                                                    @endforelse
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="accordion-item">
                                <h2 class="accordion-header" id="cashCloseExpenseSummaryHeading">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#cashCloseExpenseSummary" aria-expanded="false" aria-controls="cashCloseExpenseSummary">
                                        Reporte de egresos y saldos por metodo
                                    </button>
                                </h2>
                                <div class="accordion-collapse collapse" id="cashCloseExpenseSummary" aria-labelledby="cashCloseExpenseSummaryHeading" data-bs-parent="#cashCloseDetails">
                                    <div class="accordion-body">
                                        <div class="row g-3">
                                            <div class="col-lg-5">
                                                <table class="table table-sm table-vcenter mb-0">
                                                    <thead><tr><th>Metodo</th><th class="text-end">Egresos</th><th class="text-end">Total</th></tr></thead>
                                                    <tbody>
                                                        @forelse (($cashSummary['expense_payments'] ?? []) as $expensePayment)
                                                            <tr>
                                                                <td>{{ $expensePayment['name'] }}</td>
                                                                <td class="text-end">{{ $expensePayment['payments_count'] }}</td>
                                                                <td class="text-end fw-semibold">{{ money_format_decimal($expensePayment['total']) }}</td>
                                                            </tr>
                                                        @empty
                                                            <tr><td class="text-center text-body-secondary" colspan="3">Sin egresos registrados.</td></tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="col-lg-7">
                                                <table class="table table-sm table-vcenter mb-0">
                                                    <thead><tr><th>Metodo</th><th class="text-end">Inicial</th><th class="text-end">Ingresos</th><th class="text-end">Egresos</th><th class="text-end">Saldo</th></tr></thead>
                                                    <tbody>
                                                        @forelse (($cashSummary['method_balances'] ?? []) as $balance)
                                                            <tr>
                                                                <td>{{ $balance['name'] }}</td>
                                                                <td class="text-end">{{ money_format_decimal($balance['opening']) }}</td>
                                                                <td class="text-end">{{ money_format_decimal($balance['income']) }}</td>
                                                                <td class="text-end">{{ money_format_decimal($balance['expense']) }}</td>
                                                                <td class="text-end fw-semibold">{{ money_format_decimal($balance['balance']) }}</td>
                                                            </tr>
                                                        @empty
                                                            <tr><td class="text-center text-body-secondary" colspan="5">Sin movimientos.</td></tr>
                                                        @endforelse
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3 mt-3 align-items-end">
                            <div class="col-md-6">
                                <label class="form-label" for="closing_amount">Efectivo contado</label>
                                <input
                                    class="form-control form-control-lg text-end @error('closing_amount', 'cashClose') is-invalid @enderror"
                                    id="closing_amount"
                                    name="closing_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value="{{ old('closing_amount', number_format((float) ($cashSummary['available'] ?? 0), 2, '.', '')) }}"
                                    required
                                >
                                @error('closing_amount', 'cashClose')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-warning mb-0">
                                    Al confirmar, la caja quedara cerrada y deberas abrir una nueva para seguir vendiendo.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-link link-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary" type="submit">
                            <i class="ti ti-lock-check"></i>
                            Confirmar cierre
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    @else
        @include('pos.partials.open-register-form')
    @endif
@endsection
