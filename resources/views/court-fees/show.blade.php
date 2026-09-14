@extends('layouts.admin')
@section('title', $courtFee->name.' | '.config('app.name'))
@section('page-title', $courtFee->name)
@section('page-subtitle', 'Ítems que componen este derecho de cancha')
@section('content')
<div data-refresh-container>
<div class="card mb-3">
    <div class="card-body d-flex align-items-center gap-3">
        <span class="avatar bg-primary-lt"><i class="ti ti-cash"></i></span>
        <div>
            <div class="text-body-secondary small">Derecho de cancha</div>
            <div class="h2 mb-0">{{ $courtFee->name }}</div>
            @if ($courtFee->description)<div class="text-body-secondary mt-1">{{ $courtFee->description }}</div>@endif
        </div>
    </div>
</div>
<div class="row g-3 mb-3"><div class="col-sm-6 col-xl-4"><div class="card card-sm"><div class="card-body"><div class="text-body-secondary">Total del derecho de cancha</div><div class="h2 mb-0">Bs {{ number_format((float) ($courtFee->items_sum_cost ?? 0), 2, ',', '.') }}</div></div></div></div></div>
<div class="card mb-3">
    <div class="card-header d-flex align-items-center">
        <div><h3 class="card-title mb-0">Torneos vinculados</h3><div class="text-body-secondary small">Este derecho podrá utilizarse en cualquiera de estos torneos.</div></div>
        @can('court-fee-items.update')
            <a class="btn btn-outline-primary btn-sm ms-auto" href="{{ route('court-fees.tournaments.edit', $courtFee) }}" data-modal-url="{{ route('court-fees.tournaments.edit', $courtFee) }}" data-modal-title="Vincular torneos · {{ $courtFee->name }}">Vincular torneos</a>
        @endcan
    </div>
    <div class="card-body">
        @forelse ($courtFee->tournaments as $tournament)
            <span class="badge bg-primary-lt me-1 mb-1">{{ $tournament->name }}{{ $tournament->season ? ' · '.$tournament->season->name : '' }}</span>
        @empty
            <span class="text-body-secondary">Aún no se vinculó ningún torneo.</span>
        @endforelse
    </div>
</div>
<x-ui.table-card title="Composición">
<x-slot:actions><a class="btn btn-outline-secondary btn-sm" href="{{ route('court-fees.index') }}">Volver</a> @can('court-fee-items.update')<a class="btn btn-outline-primary btn-sm" href="{{ route('court-fees.edit', $courtFee) }}" data-modal-url="{{ route('court-fees.edit', $courtFee) }}" data-modal-title="Editar derecho: {{ $courtFee->name }}">Editar derecho</a>@endcan @can('court-fee-items.create')<a class="btn btn-primary btn-sm" href="{{ route('court-fees.items.create', $courtFee) }}" data-modal-url="{{ route('court-fees.items.create', $courtFee) }}" data-modal-title="Nuevo ítem · {{ $courtFee->name }}">Nuevo ítem</a>@endcan</x-slot:actions>
<table class="table table-hover align-middle"><thead><tr><th>Nombre</th><th class="text-end">Costo (Bs)</th><th class="text-end">Acciones</th></tr></thead><tbody>
@forelse($courtFee->items as $item)<tr><td class="fw-semibold">{{ $item->name }}</td><td class="text-end font-monospace">{{ number_format((float) $item->cost, 2, ',', '.') }}</td><td class="text-end">@can('court-fee-items.update')<a class="btn btn-outline-primary btn-sm" href="{{ route('court-fee-items.edit', $item) }}" data-modal-url="{{ route('court-fee-items.edit', $item) }}" data-modal-title="Editar ítem · {{ $courtFee->name }}">Editar</a>@endcan @can('court-fee-items.delete')<form class="d-inline" method="POST" action="{{ route('court-fee-items.destroy', $item) }}" data-confirm-delete="¿Eliminar ítem?">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm">Eliminar</button></form>@endcan</td></tr>@empty <x-ui.empty-row colspan="3" message="Este derecho de cancha todavía no tiene ítems." /> @endforelse
</tbody></table></x-ui.table-card>
</div>
@endsection
