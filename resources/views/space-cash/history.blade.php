@extends('layouts.admin')

@section('title', 'Cajas de espacios')
@section('page-title', 'Cajas de espacios')
@section('page-subtitle', 'Historial de aperturas para cobros de estancias y reservas')

@section('content')
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
                        <td class="text-end">{{ money_format_decimal($cashRegister->expenses_total ?? 0) }}</td>
                        <td class="text-end">{{ $cashRegister->closing_amount !== null ? money_format_decimal($cashRegister->closing_amount) : '-' }}</td>
                        <td class="text-end"><a class="btn btn-outline-primary btn-sm" href="{{ route('space-cash.show', $cashRegister) }}">Ver detalle</a></td>
                    </tr>
                @empty
                    <tr><td class="text-center text-body-secondary py-4" colspan="11">No hay cajas de espacios registradas.</td></tr>
                @endforelse
            </tbody>
        </table>
        <x-slot:footer>{{ $cashRegisters->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
