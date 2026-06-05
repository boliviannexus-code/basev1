@extends('layouts.admin')

@section('title', 'Aprobacion de alojamientos | '.config('app.name', 'Base Admin'))
@section('page-title', 'Aprobacion de alojamientos')
@section('page-subtitle', 'Alojamientos terminados por empresas y pendientes de aprobacion global')

@section('content')
    <x-ui.table-card title="Alojamientos por aprobar">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>Alojamiento</th>
                    <th>Modalidad</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Actualizado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($spaces as $space)
                    @php
                        $isShared = $space->spaceMode?->slug === 'compartido';
                        $statusMap = [
                            'completed' => ['Terminado', 'info'],
                            'needs_corrections' => ['Con correcciones', 'warning'],
                            'in_review' => ['En revision', 'purple'],
                            'approved' => ['Aprobado', 'primary'],
                            'active' => ['Habilitado', 'success'],
                            'inactive' => ['Inactivo', 'warning'],
                        ];
                    @endphp
                    <tr>
                        <td>{{ $space->company?->name ?: '-' }}</td>
                        <td>
                            <div class="fw-semibold">{{ $isShared ? $space->name : $space->title }}</div>
                            <div class="text-body-secondary small">{{ $space->slug }}</div>
                        </td>
                        <td>{{ $space->spaceMode?->name ?: '-' }}</td>
                        <td>{{ $isShared ? $space->sharedSpaceType?->name : $space->privateSpaceType?->name }}</td>
                        <td>
                            <span class="badge text-bg-{{ $statusMap[$space->status][1] ?? 'secondary' }}">
                                {{ $statusMap[$space->status][0] ?? $space->status }}
                            </span>
                            @if ($space->approved_at)
                                <div class="text-body-secondary small">
                                    {{ $space->approved_at->format('Y-m-d H:i') }} por {{ $space->approvedBy?->name ?: 'Sistema' }}
                                </div>
                            @endif
                        </td>
                        <td>{{ $space->updated_at?->format('Y-m-d H:i') }}</td>
                        <td class="text-end">
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.spaces.show', $space) }}">
                                <i class="ti ti-eye me-1"></i>Ver
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="7" message="No hay alojamientos terminados pendientes de aprobacion." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $spaces->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
