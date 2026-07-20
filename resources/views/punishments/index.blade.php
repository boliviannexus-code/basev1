@extends('layouts.admin')

@section('title', 'Castigos | '.config('app.name', 'Base Admin'))
@section('page-title', 'Castigos')
@section('page-subtitle', 'Sanciones independientes de jornadas y partidos')

@section('content')
    <x-ui.table-card title="Listado de castigos">
        <x-slot:actions>
            @can('punishments.create')
                <a class="btn btn-primary btn-sm" href="{{ route('punishments.create') }}">Nuevo castigo</a>
            @endcan
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Jugador</th>
                    <th>Articulo</th>
                    <th>Duracion</th>
                    <th>Estado</th>
                    <th>Motivo</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($punishments as $punishment)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $punishment->player?->full_name ?? '-' }}</div>
                            <div class="text-body-secondary small">{{ $punishment->player?->internal_code ?? '-' }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $punishment->article?->number ?? '-' }}</div>
                            <div class="text-body-secondary small">{{ str($punishment->article?->detail ?? '')->limit(70) }}</div>
                        </td>
                        <td>
                            @if ($punishment->duration_type === 'indefinite')
                                Indefinido
                            @else
                                {{ $punishment->duration_value }} {{ $punishment->duration_type === 'months' ? 'mes(es)' : 'ano(s)' }}
                            @endif
                            <div class="text-body-secondary small">
                                {{ $punishment->starts_on?->format('d/m/Y') }} - {{ $punishment->ends_on?->format('d/m/Y') ?? 'sin fin' }}
                            </div>
                        </td>
                        <td>
                            @if ($punishment->status === 'active')
                                <span class="badge text-bg-danger">Activo</span>
                            @elseif ($punishment->status === 'lift_requested')
                                <span class="badge text-bg-warning">Pendiente aprobacion</span>
                            @else
                                <span class="badge text-bg-success">Quitado</span>
                            @endif
                        </td>
                        <td>{{ str($punishment->reason)->limit(90) }}</td>
                        <td class="text-end" style="min-width: 18rem;">
                            @if ($punishment->status === 'active')
                                @can('punishments.request-lift')
                                    <form class="d-inline-flex gap-1" method="POST" action="{{ route('punishments.request-lift', $punishment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input class="form-control form-control-sm" name="lift_reason" placeholder="Motivo para quitar" required>
                                        <button class="btn btn-outline-warning btn-sm" type="submit">Solicitar</button>
                                    </form>
                                @endcan
                            @elseif ($punishment->status === 'lift_requested')
                                <div class="text-body-secondary small mb-1">{{ $punishment->lift_reason }}</div>
                                @can('punishments.approve-lift')
                                    <form class="d-inline" method="POST" action="{{ route('punishments.review-lift', $punishment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="decision" value="approve">
                                        <button class="btn btn-success btn-sm" type="submit">Aprobar</button>
                                    </form>
                                    <form class="d-inline" method="POST" action="{{ route('punishments.review-lift', $punishment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="decision" value="reject">
                                        <button class="btn btn-outline-danger btn-sm" type="submit">Rechazar</button>
                                    </form>
                                @endcan
                            @else
                                <span class="text-body-secondary small">Revisado por {{ $punishment->reviewedBy?->name ?? '-' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="6" message="No hay castigos registrados." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $punishments->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
