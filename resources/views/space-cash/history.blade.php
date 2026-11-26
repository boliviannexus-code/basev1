@extends('layouts.admin')

@section('title', 'Cajas de espacios')
@section('page-title', 'Cajas de espacios')
@section('page-subtitle', (auth()->user()->hasRole('super_admin') || auth()->user()->can('space-cash.view-all'))
    ? 'Historial de cajas de todos los usuarios de la empresa'
    : 'Historial de tus cajas para cobros de estancias y reservas')

@section('content')
    <form method="GET" action="{{ route('space-cash.history') }}" class="card mb-3">
        <div class="card-body"><div class="row g-3 align-items-end">
            @if ($canViewAll)
                <div class="col-md-3"><label class="form-label" for="cash-history-user">Usuario</label>
                    <select id="cash-history-user" name="user_id" class="form-select"><option value="">Todos</option>
                        @foreach ($users as $user)<option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>@endforeach
                    </select>
                </div>
            @endif
            <div class="col-md-2"><label class="form-label" for="cash-history-status">Estado</label>
                <select id="cash-history-status" name="status" class="form-select"><option value="">Todos</option><option value="open" @selected(($filters['status'] ?? '') === 'open')>Abierta</option><option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Cerrada</option></select>
            </div>
            <div class="col-md-2"><label class="form-label" for="cash-history-from">Apertura desde</label><input class="form-control" type="date" id="cash-history-from" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label" for="cash-history-to">Apertura hasta</label><input class="form-control" type="date" id="cash-history-to" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
            <div class="col-auto"><button class="btn btn-primary" type="submit">Filtrar</button> <a class="btn btn-outline-secondary" href="{{ route('space-cash.history') }}">Limpiar</a></div>
        </div></div>
    </form>
    <x-ui.table-card title="Listado de cajas de espacios">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Apertura</th>
                    <th>Cierre</th>
                    <th>Usuario</th>
                    <th>Estado</th>
                    <th class="text-end">Directos</th>
                    <th class="text-end">Estancias</th>
                    <th class="text-end">Reservas</th>
                    <th class="text-end">Cobros</th>
                    <th class="text-end">Descuentos</th>
                    <th class="text-end">Egresos</th>
                    <th class="text-end">Cierre contado</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($cashRegisters as $cashRegister)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $cashRegister->opened_at?->format('Y-m-d H:i') }}</div>
                            <div class="text-body-secondary small">{{ $cashRegister->company?->name }}</div>
                        </td>
                        <td>{{ $cashRegister->closed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                        <td>{{ $cashRegister->user?->name ?? '-' }}</td>
                        <td><span class="badge text-bg-{{ $cashRegister->status === 'open' ? 'success' : 'secondary' }}">{{ $cashRegister->status === 'open' ? 'Abierta' : 'Cerrada' }}</span></td>
                        <td class="text-end">{{ money_format_decimal($cashRegister->direct_total ?? 0) }}</td>
                        <td class="text-end">{{ money_format_decimal($cashRegister->lodging_total ?? 0) }}</td>
                        <td class="text-end">{{ money_format_decimal($cashRegister->reservation_total ?? 0) }}</td>
                        <td class="text-end fw-semibold">{{ money_format_decimal($cashRegister->income_total ?? 0) }}</td>
                        <td class="text-end">{{ money_format_decimal($cashRegister->discount_total ?? 0) }}</td>
                        <td class="text-end">{{ money_format_decimal($cashRegister->expenses_total ?? 0) }}</td>
                        <td class="text-end">{{ $cashRegister->closing_amount !== null ? money_format_decimal($cashRegister->closing_amount) : '-' }}</td>
                        <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="{{ route('space-cash.show', $cashRegister) }}">Ver detalle</a></td>
                    </tr>
                @empty
                    <tr><td class="text-center text-body-secondary py-4" colspan="12">No hay cajas de espacios registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
        <x-slot:footer>{{ $cashRegisters->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
