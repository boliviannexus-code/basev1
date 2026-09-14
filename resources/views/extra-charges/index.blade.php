@extends('layouts.admin')
@section('title', 'Cargos extra | '.config('app.name'))
@section('page-title', 'Cargos extra')
@section('page-subtitle', 'Cargos adicionales distribuidos en derechos de cancha')
@section('content')
<x-ui.table-card title="Listado de cargos" data-refresh-container>
<x-slot:actions>@can('court-fee-items.create')<a class="btn btn-primary btn-sm" href="{{ route('extra-charges.create') }}" data-modal-url="{{ route('extra-charges.create') }}" data-modal-title="Nuevo cargo extra">Nuevo cargo</a>@endcan</x-slot:actions>
<table class="table table-hover align-middle"><thead><tr><th>Cargo</th><th class="text-end">Monto/equipo</th><th class="text-end">Cuotas</th><th class="text-end">Monto/cuota</th><th>Asignado a</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead><tbody>
@forelse($charges as $charge)<tr>
<td><div class="fw-semibold">{{ $charge->name }}</div><div class="small text-body-secondary">{{ $charge->description ?: 'Sin descripción' }}</div></td>
<td class="text-end">Bs {{ number_format((float)$charge->amount_per_team, 2, ',', '.') }}</td><td class="text-end">{{ $charge->installments }}</td><td class="text-end">Bs {{ number_format((float)$charge->amount_per_team / $charge->installments, 2, ',', '.') }}</td>
<td>@forelse($charge->assignments->groupBy('tournament_id') as $assignments)<div class="small"><strong>{{ $assignments->first()->tournament?->name }}</strong>: {{ $assignments->contains('all_teams', true) ? 'Todos los equipos' : $assignments->pluck('team.name')->filter()->join(', ') }}</div>@empty<span class="text-body-secondary small">Sin asignar</span>@endforelse</td>
<td><span class="badge text-bg-{{ $charge->is_active ? 'success' : 'secondary' }}">{{ $charge->is_active ? 'Activo' : 'Inactivo' }}</span></td>
<td class="text-end">@can('court-fee-items.update')<a class="btn btn-outline-secondary btn-sm" href="{{ route('extra-charges.assignments.edit', $charge) }}" data-modal-url="{{ route('extra-charges.assignments.edit', $charge) }}" data-modal-title="Asignar cargo · {{ $charge->name }}">Asignar</a> <a class="btn btn-outline-primary btn-sm" href="{{ route('extra-charges.edit', $charge) }}" data-modal-url="{{ route('extra-charges.edit', $charge) }}" data-modal-title="Editar cargo extra">Editar</a>@endcan</td>
</tr>@empty <x-ui.empty-row colspan="7" message="No hay cargos extra registrados." /> @endforelse
</tbody></table><x-slot:footer>{{ $charges->links() }}</x-slot:footer></x-ui.table-card>
@endsection
