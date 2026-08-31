@extends('layouts.admin')
@section('title', 'Derechos de cancha | '.config('app.name'))
@section('page-title', 'Derechos de cancha')
@section('page-subtitle', 'Tarifas disponibles y composición de cada una')
@section('content')
<x-ui.table-card title="Derechos de cancha" data-refresh-container>
    <x-slot:actions>@can('court-fee-items.create')<a class="btn btn-primary btn-sm" href="{{ route('court-fees.create') }}" data-modal-url="{{ route('court-fees.create') }}" data-modal-title="Nuevo derecho de cancha">Nuevo derecho</a>@endcan</x-slot:actions>
    <table class="table table-hover align-middle"><thead><tr><th>Nombre</th><th>Torneos vinculados</th><th>Ítems</th><th class="text-end">Total (Bs)</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead><tbody>
    @forelse($fees as $fee)<tr><td><div class="fw-semibold">{{ $fee->name }}</div><div class="text-body-secondary small">{{ $fee->description ?: 'Sin descripción' }}</div></td><td>@forelse($fee->tournaments as $tournament)<span class="badge bg-primary-lt me-1 mb-1">{{ $tournament->name }}</span>@empty<span class="text-body-secondary small">Sin torneos vinculados</span>@endforelse</td><td>{{ $fee->items_count }}</td><td class="text-end font-monospace">{{ number_format((float) ($fee->items_sum_cost ?? 0), 2, ',', '.') }}</td><td><span class="badge text-bg-{{ $fee->is_active ? 'success' : 'secondary' }}">{{ $fee->is_active ? 'Activo' : 'Inactivo' }}</span></td><td class="text-end"><a class="btn btn-outline-secondary btn-sm" href="{{ route('court-fees.show', $fee) }}">Administrar</a> @can('court-fee-items.update')<a class="btn btn-outline-primary btn-sm" href="{{ route('court-fees.edit', $fee) }}" data-modal-url="{{ route('court-fees.edit', $fee) }}" data-modal-title="Editar derecho de cancha">Editar</a>@endcan</td></tr>
    @empty <x-ui.empty-row colspan="6" message="No hay derechos de cancha registrados." /> @endforelse
    </tbody></table><x-slot:footer>{{ $fees->links() }}</x-slot:footer>
</x-ui.table-card>
@endsection
