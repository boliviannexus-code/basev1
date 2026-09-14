@extends('layouts.admin')
@section('title', 'Derecho jornadas | '.config('app.name'))
@section('page-title', 'Derecho jornadas')
@section('page-subtitle', 'Generación de cargos para los equipos programados por jornada')
@section('content')
<x-ui.table-card title="Jornadas registradas">
    <table class="table table-hover align-middle">
        <thead><tr><th>Jornada</th><th>Gestión</th><th>Fecha</th><th>Partidos</th><th>Estado jornada</th><th>Estado derecho</th><th class="text-end">Acción</th></tr></thead>
        <tbody>
        @forelse($matchdays as $matchday)
            <tr>
                <td><div class="fw-semibold">{{ $matchday->name ?: 'Jornada '.$matchday->number }}</div><div class="text-body-secondary small">N.º {{ $matchday->number }}</div></td>
                <td>{{ $matchday->season?->name ?? '-' }}</td>
                <td>{{ $matchday->scheduled_date?->format('d/m/Y') ?? 'Sin fecha general' }}</td>
                <td>{{ $matchday->fixture_matches_count }} partido(s)</td>
                <td><span class="badge text-bg-{{ $matchday->status === 'finalized' ? 'success' : 'secondary' }}">{{ $matchday->status === 'finalized' ? 'Finalizada' : 'Borrador' }}</span></td>
                @php($feeStatus = $matchday->courtFeeStatement?->status ?? 'pending')
                <td><span class="badge text-bg-{{ $feeStatus === 'consolidated' ? 'success' : ($feeStatus === 'needs_reconsolidation' ? 'warning' : 'secondary') }}">{{ $feeStatus === 'consolidated' ? 'Consolidado' : ($feeStatus === 'needs_reconsolidation' ? 'Pendiente de reconsolidación' : 'Pendiente') }}</span></td>
                <td class="text-end"><a class="btn btn-primary btn-sm" href="{{ route('matchday-court-fees.show', $matchday) }}"><i class="ti ti-receipt me-1"></i>Generar derecho</a></td>
            </tr>
        @empty
            <x-ui.empty-row colspan="7" message="No hay jornadas registradas." />
        @endforelse
        </tbody>
    </table>
    <x-slot:footer>{{ $matchdays->links() }}</x-slot:footer>
</x-ui.table-card>
@endsection
