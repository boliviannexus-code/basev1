@extends('layouts.admin')

@section('title', 'Caja de espacios')
@section('page-title', 'Caja de espacios')
@section('page-subtitle', 'Caja independiente para cobros de estancias y reservas')

@section('content')
    @if ($openRegister || ($companyHasOpenRegister ?? false))
        @unless ($openRegister)
            <div class="alert alert-info">
                No tienes una caja de espacios abierta en tu sesion. Puedes registrar ingresos o egresos usando el codigo de caja de un usuario que si tenga caja abierta.
            </div>
        @endunless
        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-xl">
                <x-ui.stat-card label="Base inicial" :value="money_format_decimal($cashSummary['opening'] ?? 0)" icon="ti ti-cash" />
            </div>
            <div class="col-sm-6 col-xl">
                <x-ui.stat-card label="Ingresos directos" :value="money_format_decimal($cashSummary['direct_total'] ?? 0)" icon="ti ti-cash-plus" tone="success" />
            </div>
            <div class="col-sm-6 col-xl">
                <x-ui.stat-card label="Cobros estancia" :value="money_format_decimal($cashSummary['lodging_total'] ?? 0)" icon="ti ti-home-dollar" tone="success" />
            </div>
            <div class="col-sm-6 col-xl">
                <x-ui.stat-card label="Cobros reserva" :value="money_format_decimal($cashSummary['reservation_total'] ?? 0)" icon="ti ti-calendar-dollar" tone="success" />
            </div>
            <div class="col-sm-6 col-xl">
                <x-ui.stat-card label="Egresos" :value="money_format_decimal($cashSummary['expenses'] ?? 0)" icon="ti ti-cash-banknote-off" />
            </div>
            <div class="col-sm-6 col-xl">
                <x-ui.stat-card label="Efectivo esperado" :value="money_format_decimal($cashSummary['available'] ?? 0)" icon="ti ti-report-money" />
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-3 col-xl-3">
                        <div class="text-body-secondary">Empresa</div>
                        <div class="fw-semibold">{{ $openRegister?->company?->name ?? auth()->user()?->company?->name ?? 'Empresa' }}</div>
                    </div>
                    <div class="col-md-3 col-xl-3">
                        <div class="text-body-secondary">Usuario</div>
                        <div class="fw-semibold">{{ $openRegister?->user?->name ?? 'Caja con codigo de otro usuario' }}</div>
                    </div>
                    <div class="col-md-3 col-xl-2">
                        <div class="text-body-secondary">Apertura</div>
                        <div class="fw-semibold">{{ $openRegister?->opened_at?->format('Y-m-d H:i') ?? '-' }}</div>
                    </div>
                    <div class="col-md-12 col-xl-4">
                        <div class="d-flex flex-wrap flex-md-nowrap gap-2 justify-content-xl-end">
                            <button class="btn btn-outline-success btn-sm text-nowrap" type="button" data-bs-toggle="modal" data-bs-target="#spaceCashIncomeModal">
                                <i class="ti ti-cash-plus"></i>
                                Ingreso
                            </button>
                            <button class="btn btn-outline-danger btn-sm text-nowrap" type="button" data-bs-toggle="modal" data-bs-target="#spaceCashExpenseModal">
                                <i class="ti ti-cash-banknote-off"></i>
                                Egreso
                            </button>
                            @if ($openRegister)
                                <button class="btn btn-primary btn-sm text-nowrap" type="button" data-bs-toggle="modal" data-bs-target="#spaceCashCloseModal">
                                    <i class="ti ti-lock"></i>
                                    Cerrar caja
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <x-ui.table-card title="Pagos por metodo">
                    <table class="table table-sm table-vcenter mb-0">
                        <thead><tr><th>Metodo</th><th class="text-end">Pagos</th><th class="text-end">Total BOB</th></tr></thead>
                        <tbody>
                            @forelse (($cashSummary['payments'] ?? []) as $payment)
                                <tr>
                                    <td>{{ $payment['name'] }}</td>
                                    <td class="text-end">{{ $payment['payments_count'] }}</td>
                                    <td class="text-end fw-semibold">{{ money_format_decimal($payment['total']) }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-center text-body-secondary" colspan="3">Sin cobros registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-ui.table-card>
            </div>
            <div class="col-lg-7">
                <x-ui.table-card title="Cobros de estancias">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Comprobante</th><th>Fecha</th><th>Estancia</th><th>Metodo</th><th class="text-end">Total BOB</th></tr></thead>
                        <tbody>
                            @forelse (($cashSummary['lodging_payments'] ?? []) as $payment)
                                <tr>
                                    <td class="fw-semibold">{{ $payment->receipt_number }}</td>
                                    <td>{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>{{ $payment->stay?->holderGuest?->full_name ?? 'Estancia' }}</td>
                                    <td>{{ $payment->paymentMethod?->name ?? '-' }}</td>
                                    <td class="text-end fw-semibold">{{ money_format_decimal($payment->amount_bob) }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-center text-body-secondary" colspan="5">Sin cobros de estancias.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-ui.table-card>
            </div>
        </div>

        <x-ui.table-card title="Cobros de reservas" class="mt-3">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Comprobante</th><th>Fecha</th><th>Reserva</th><th>Metodo</th><th>Referencia</th><th class="text-end">Total BOB</th></tr></thead>
                <tbody>
                    @forelse (($cashSummary['reservation_payments'] ?? []) as $payment)
                        <tr>
                            <td class="fw-semibold">{{ $payment->receipt_number }}</td>
                            <td>{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $payment->reservationGroup?->code ?? 'Reserva' }}</td>
                            <td>{{ $payment->paymentMethod?->name ?? '-' }}</td>
                            <td>{{ $payment->reference ?: '-' }}</td>
                            <td class="text-end fw-semibold">{{ money_format_decimal($payment->amount_bob) }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-body-secondary" colspan="6">Sin cobros de reservas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.table-card>

        <x-ui.table-card title="Ingresos directos" class="mt-3">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Comprobante</th><th>Fecha</th><th>Categoria</th><th class="text-end">Cantidad</th><th>Detalle</th><th>Metodo</th><th>Referencia</th><th class="text-end">Total BOB</th></tr></thead>
                <tbody>
                    @forelse (($cashSummary['direct_incomes'] ?? []) as $income)
                        <tr>
                            <td class="fw-semibold">{{ $income->receipt_number }}</td>
                            <td>{{ $income->received_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $income->category?->name ?? '-' }}</td>
                            <td class="text-end">{{ number_format((float) ($income->quantity ?? 1), 2) }}</td>
                            <td>{{ $income->detail }}</td>
                            <td>{{ $income->paymentMethod?->name ?? '-' }}</td>
                            <td>{{ $income->reference ?: '-' }}</td>
                            <td class="text-end fw-semibold">{{ money_format_decimal($income->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-body-secondary" colspan="8">Sin ingresos directos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.table-card>

        <div class="modal modal-blur fade space-cash-entry-modal" id="spaceCashIncomeModal" tabindex="-1" aria-hidden="true" @if ($errors->spaceCashIncome->any()) data-show-cash-income-modal @endif>
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <form class="modal-content" method="POST" action="{{ route('space-cash.incomes.store') }}" autocomplete="off" novalidate data-ajax-form data-requires-transaction-pin>
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title">Registrar ingreso</h2>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="space-cash-entry-grid">
                            <div class="space-cash-field">
                                <label class="form-label" for="space_cash_income_category">Categoria</label>
                                <select class="form-select @error('extra_charge_category_id', 'spaceCashIncome') is-invalid @enderror" id="space_cash_income_category" name="extra_charge_category_id" data-remote-category-select data-url="{{ route('extra-charge-categories.autocomplete') }}" data-placeholder="Buscar categoria" required>
                                    <option value="">Seleccionar categoria</option>
                                    @foreach ($expenseCategories as $category)
                                        <option value="{{ $category->id }}" data-default-unit-price="{{ $category->default_unit_price }}" @selected((int) old('extra_charge_category_id') === (int) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('extra_charge_category_id', 'spaceCashIncome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="space-cash-field">
                                <label class="form-label" for="space_cash_income_payment_method">Metodo de pago</label>
                                <select class="form-select @error('payment_method_id', 'spaceCashIncome') is-invalid @enderror" id="space_cash_income_payment_method" name="payment_method_id" required>
                                    <option value="">Seleccionar metodo</option>
                                    @foreach ($paymentMethods as $paymentMethod)
                                        <option value="{{ $paymentMethod->id }}" @selected((int) old('payment_method_id') === (int) $paymentMethod->id)>{{ $paymentMethod->name }}</option>
                                    @endforeach
                                </select>
                                @error('payment_method_id', 'spaceCashIncome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="space-cash-field">
                                <label class="form-label" for="space_cash_income_responsible_name">Encargado</label>
                                <input class="form-control @error('responsible_name', 'spaceCashIncome') is-invalid @enderror" id="space_cash_income_responsible_name" name="responsible_name" value="{{ old('responsible_name', auth()->user()?->name) }}">
                                @error('responsible_name', 'spaceCashIncome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="space-cash-field">
                                <label class="form-label" for="space_cash_income_quantity">Cantidad</label>
                                <input class="form-control text-end @error('quantity', 'spaceCashIncome') is-invalid @enderror" id="space_cash_income_quantity" name="quantity" type="number" min="0.5" step="0.5" value="{{ old('quantity', '1') }}" required>
                                @error('quantity', 'spaceCashIncome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="space-cash-field">
                                <label class="form-label" for="space_cash_income_amount">Monto</label>
                                <input class="form-control text-end @error('amount', 'spaceCashIncome') is-invalid @enderror" id="space_cash_income_amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required>
                                @error('amount', 'spaceCashIncome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="space-cash-field">
                                <label class="form-label" for="space_cash_income_reference">Referencia</label>
                                <input class="form-control @error('reference', 'spaceCashIncome') is-invalid @enderror" id="space_cash_income_reference" name="reference" value="{{ old('reference') }}">
                                @error('reference', 'spaceCashIncome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="space-cash-field space-cash-field-wide">
                                <label class="form-label" for="space_cash_income_detail">Detalle</label>
                                <textarea class="form-control @error('detail', 'spaceCashIncome') is-invalid @enderror" id="space_cash_income_detail" name="detail" rows="2">{{ old('detail') }}</textarea>
                                @error('detail', 'spaceCashIncome')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-link link-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-success" type="submit">Registrar ingreso</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal modal-blur fade space-cash-entry-modal" id="spaceCashExpenseModal" tabindex="-1" aria-hidden="true" @if ($errors->spaceCashExpense->any()) data-show-cash-expense-modal @endif>
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <form class="modal-content" method="POST" action="{{ route('space-cash.expenses.store') }}" autocomplete="off" novalidate data-ajax-form data-requires-transaction-pin>
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title">Registrar egreso</h2>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-2 mb-2">
                            @if ($openRegister)
                                Disponible en efectivo: <strong>{{ money_format_decimal($cashSummary['available'] ?? 0) }}</strong>
                            @else
                                El efectivo disponible se validara con la caja del usuario dueño del codigo.
                            @endif
                        </div>
                        <div class="space-cash-entry-grid">
                            <div class="space-cash-field">
                                <label class="form-label" for="space_cash_expense_category">Categoria</label>
                                <select class="form-select @error('extra_charge_category_id', 'spaceCashExpense') is-invalid @enderror" id="space_cash_expense_category" name="extra_charge_category_id" data-remote-category-select data-url="{{ route('extra-charge-categories.autocomplete') }}" data-placeholder="Buscar categoria" required>
                                    <option value="">Seleccionar categoria</option>
                                    @foreach ($expenseCategories as $category)
                                        <option value="{{ $category->id }}" data-default-unit-price="{{ $category->default_unit_price }}" @selected((int) old('extra_charge_category_id') === (int) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('extra_charge_category_id', 'spaceCashExpense')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="space-cash-field">
                                <label class="form-label" for="space_cash_expense_payment_method">Metodo de pago</label>
                                <select class="form-select @error('payment_method_id', 'spaceCashExpense') is-invalid @enderror" id="space_cash_expense_payment_method" name="payment_method_id" required>
                                    <option value="">Seleccionar metodo</option>
                                    @foreach ($paymentMethods as $paymentMethod)
                                        <option value="{{ $paymentMethod->id }}" @selected((int) old('payment_method_id') === (int) $paymentMethod->id)>{{ $paymentMethod->name }}</option>
                                    @endforeach
                                </select>
                                @error('payment_method_id', 'spaceCashExpense')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="space-cash-field">
                                <label class="form-label" for="space_cash_expense_responsible_name">Encargado</label>
                                <input class="form-control @error('responsible_name', 'spaceCashExpense') is-invalid @enderror" id="space_cash_expense_responsible_name" name="responsible_name" value="{{ old('responsible_name', auth()->user()?->name) }}" required>
                                @error('responsible_name', 'spaceCashExpense')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="space-cash-field">
                                <label class="form-label" for="space_cash_expense_quantity">Cantidad</label>
                                <input class="form-control text-end @error('quantity', 'spaceCashExpense') is-invalid @enderror" id="space_cash_expense_quantity" name="quantity" type="number" min="0.5" step="0.5" value="{{ old('quantity', '1') }}" required>
                                @error('quantity', 'spaceCashExpense')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="space-cash-field">
                                <label class="form-label" for="space_cash_expense_amount">Monto</label>
                                <input class="form-control text-end @error('amount', 'spaceCashExpense') is-invalid @enderror" id="space_cash_expense_amount" name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required>
                                @error('amount', 'spaceCashExpense')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="space-cash-field space-cash-field-wide">
                                <label class="form-label" for="space_cash_expense_detail">Detalle</label>
                                <textarea class="form-control @error('detail', 'spaceCashExpense') is-invalid @enderror" id="space_cash_expense_detail" name="detail" rows="2">{{ old('detail') }}</textarea>
                                @error('detail', 'spaceCashExpense')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-link link-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-danger" type="submit">Registrar egreso</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($openRegister)
            <div class="modal modal-blur fade" id="spaceCashCloseModal" tabindex="-1" aria-hidden="true" @if ($errors->spaceCashClose->any()) data-show-cash-close-modal @endif>
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" method="POST" action="{{ route('space-cash.close') }}" autocomplete="off" novalidate>
                        @csrf
                        <div class="modal-header">
                            <h2 class="modal-title">Cerrar caja de espacios</h2>
                            <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label" for="space_cash_closing_amount">Efectivo contado</label>
                            <input class="form-control form-control-lg text-end @error('closing_amount', 'spaceCashClose') is-invalid @enderror" id="space_cash_closing_amount" name="closing_amount" type="number" min="0" step="0.01" value="{{ old('closing_amount', number_format((float) ($cashSummary['available'] ?? 0), 2, '.', '')) }}" required>
                            @error('closing_amount', 'spaceCashClose')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-link link-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-primary" type="submit">Confirmar cierre</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    @else
        <div class="card form-panel">
            <div class="card-header"><h3 class="card-title">Abrir caja de espacios</h3></div>
            <div class="card-body">
                <form method="POST" action="{{ route('space-cash.open') }}" autocomplete="off" novalidate>
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Caja</label>
                            <div class="form-control-plaintext fw-semibold">{{ auth()->user()?->company?->name ?? 'Empresa' }} · {{ auth()->user()?->name }}</div>
                            <div class="text-body-secondary small">Esta caja registra cobros de estados de cuenta de estancias y reservas.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="space_cash_opening_amount">Monto inicial</label>
                            <input class="form-control text-end @error('opening_amount') is-invalid @enderror" id="space_cash_opening_amount" name="opening_amount" type="number" min="0" step="0.01" value="{{ old('opening_amount', '0.00') }}" required>
                            @error('opening_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="d-flex justify-content-end mt-4">
                        <button class="btn btn-primary" type="submit">Abrir caja</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection
