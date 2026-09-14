@extends('layouts.admin')
@section('title', 'Generar derecho | '.config('app.name'))
@section('page-title', 'Generar derecho de cancha')
@section('page-subtitle', ($matchday->name ?: 'Jornada '.$matchday->number).' · '.($matchday->season?->name ?? 'Sin gestión'))
@section('content')
<div data-refresh-container>
<div class="d-flex justify-content-between align-items-center mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="{{ route('matchday-court-fees.index') }}"><i class="ti ti-arrow-left me-1"></i>Jornadas</a>
    <div class="d-flex gap-2 align-items-center">
        @php
            if ($statement->status === 'consolidated') {
                $statementStyle = 'success';
                $statementLabel = 'Consolidado';
            } elseif ($statement->status === 'needs_reconsolidation') {
                $statementStyle = 'warning';
                $statementLabel = 'Pendiente de reconsolidación';
            } else {
                $statementStyle = 'secondary';
                $statementLabel = 'Pendiente de consolidación';
            }
        @endphp
        <span class="badge text-bg-{{ $statementStyle }}">{{ $statementLabel }}</span>
        @can('court-fee-items.update')
            @if($statement->status === 'pending')
                <a class="btn btn-primary btn-sm" href="{{ route('matchday-court-fees.generate-form', $matchday) }}" data-modal-url="{{ route('matchday-court-fees.generate-form', $matchday) }}" data-modal-title="Seleccionar cargos extra"><i class="ti ti-receipt me-1"></i>Aplicar cargos y cuotas</a>
                <form method="POST" action="{{ route('matchday-court-fees.consolidate', $matchday) }}" data-confirm-delete="¿Consolidar derecho de cancha?" data-confirm-button-text="Sí, consolidar" data-confirm-text="Después de consolidar no podrás modificar los cargos directamente.">@csrf<button class="btn btn-success btn-sm" type="submit"><i class="ti ti-lock me-1"></i>Consolidar</button></form>
            @else
                @if($statement->status === 'consolidated')
                    <a class="btn btn-success btn-sm" href="{{ route('matchday-court-fees.pdf', $matchday) }}" target="_blank"><i class="ti ti-printer me-1"></i>Imprimir PDF</a>
                @endif
                <form method="POST" action="{{ route('matchday-court-fees.reopen', $matchday) }}" data-confirm-delete="¿Habilitar edición?" data-confirm-button-text="Sí, editar" data-confirm-text="El derecho volverá a estado pendiente hasta que se consolide nuevamente.">@csrf @method('PATCH')<button class="btn btn-outline-primary btn-sm" type="submit"><i class="ti ti-edit me-1"></i>Editar</button></form>
            @endif
        @endcan
    </div>
</div>
<x-ui.table-card title="Equipos programados y cargos">
    <table class="table table-hover align-middle">
        <thead><tr><th>Equipo</th><th>Torneo</th>@foreach($extraChargeColumns as $column)<th class="text-end"><div>{{ $column->name }}</div>@can('court-fee-items.update')@if($statement->status === 'pending')<form class="mt-1" method="POST" action="{{ route('matchday-court-fees.installments.destroy-charge', [$matchday, $column]) }}" data-confirm-delete="¿Quitar {{ $column->name }} a todos?" data-confirm-button-text="Sí, quitar a todos" data-confirm-text="Se anularán las cuotas de este cargo para todos los equipos de la jornada, conservando el historial.">@csrf @method('DELETE')<button class="btn btn-link text-danger btn-sm p-0" type="submit"><i class="ti ti-x me-1"></i>Quitar a todos</button></form>@endif @endcan</th>@endforeach @foreach($accountChargeColumns as $column)<th class="text-end"><div>{{ $column->description }}</div><small class="text-body-secondary">Pendiente anterior</small></th>@endforeach <th class="text-end">Total equipo (Bs)</th></tr></thead>
        <tbody>
        @forelse($teamCharges as $charge)
            @php
                $extras = $extraInstallments->get($charge['key'], collect());
                $pendingCharges = $accountCharges->get($charge['key'], collect());
                $teamTotal = $charge['total'] + (float) $extras->sum('amount') + (float) $pendingCharges->sum('total_amount');
            @endphp
            <tr>
                <td class="fw-semibold">{{ $charge['team']->name }}</td>
                <td>{{ $charge['tournament']?->name ?? '-' }}</td>
                @foreach($extraChargeColumns as $column)
                    @php($extra = $extras->firstWhere('extra_charge_id', $column->id))
                    <td class="text-end">
                        @if($extra)
                            <div class="fw-semibold font-monospace">Bs {{ number_format((float) $extra->amount, 2, ',', '.') }}</div>
                            <span class="badge bg-primary-lt">Cuota {{ $extra->installment_number }}/{{ $extra->extraCharge->installments }}</span>
                            @can('court-fee-items.update') @if($statement->status === 'pending')
                                <form class="mt-1" method="POST" action="{{ route('matchday-court-fees.installments.destroy', $extra) }}" data-confirm-delete="¿Quitar esta cuota?" data-confirm-button-text="Sí, quitar" data-confirm-text="La cuota será anulada, conservará su historial y dejará de contar para futuras aplicaciones.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-link text-danger btn-sm p-0" type="submit"><i class="ti ti-x me-1"></i>Quitar</button>
                                </form>
                            @endif @endcan
                        @else
                            <span class="text-body-secondary">-</span>
                        @endif
                    </td>
                @endforeach
                @foreach($accountChargeColumns as $column)
                    @php($pending = $pendingCharges->where('concept_key', $column->concept_key))
                    <td class="text-end">@if($pending->isNotEmpty())<div class="fw-semibold font-monospace">Bs {{ number_format((float)$pending->sum('total_amount'), 2, ',', '.') }}</div><small class="text-body-secondary">{{ $pending->sum('quantity') }} × Bs {{ number_format((float)$pending->first()->unit_amount, 2, ',', '.') }}<br>{{ $pending->pluck('sourceMatchday.name')->filter()->unique()->join(', ') }}</small>@else<span class="text-body-secondary">-</span>@endif</td>
                @endforeach
                <td class="text-end fw-bold font-monospace">{{ number_format($teamTotal, 2, ',', '.') }}</td>
            </tr>
        @empty
            <x-ui.empty-row :colspan="3 + $extraChargeColumns->count() + $accountChargeColumns->count()" message="La jornada no tiene equipos programados." />
        @endforelse
        </tbody>
        @if($teamCharges->isNotEmpty())<tfoot><tr class="fw-bold"><td colspan="{{ 2 + $extraChargeColumns->count() + $accountChargeColumns->count() }}">Total de la jornada</td><td class="text-end">Bs {{ number_format((float) $teamCharges->sum('total') + (float)$extraInstallments->flatten(1)->sum('amount') + (float)$accountCharges->flatten(1)->sum('total_amount'), 2, ',', '.') }}</td></tr></tfoot>@endif
    </table>
</x-ui.table-card>
</div>
@endsection
