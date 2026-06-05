@extends('layouts.admin')

@section('title', 'Huellas | '.config('app.name', 'Base Admin'))
@section('page-title', 'Huellas')
@section('page-subtitle', 'Plantillas biometricas asociadas a usuarios')

@section('content')
    <x-ui.table-card title="Plantillas de huella" data-refresh-container>
        <x-slot:actions>
            @can('fingerprint-templates.create')
                <a class="btn btn-primary btn-sm" href="{{ route('fingerprint-templates.create') }}" data-modal-url="{{ route('fingerprint-templates.create') }}" data-modal-title="Nueva huella">Nueva huella</a>
            @endcan
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Usuario</th>
                    <th>Liga deportiva</th>
                    <th>Formato</th>
                    <th>Registrada</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($templates as $template)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $template->user?->name ?? '-' }}</div>
                            <div class="text-body-secondary small">{{ $template->user?->email ?? '-' }}</div>
                        </td>
                        <td>{{ $template->user?->company?->name ?? 'Sin liga deportiva' }}</td>
                        <td>{{ $template->format ?: '-' }}</td>
                        <td>{{ $template->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('fingerprint-templates.show', $template) }}" data-modal-url="{{ route('fingerprint-templates.show', $template) }}" data-modal-title="Detalle de huella">Ver</a>
                            @can('fingerprint-templates.update')
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('fingerprint-templates.edit', $template) }}" data-modal-url="{{ route('fingerprint-templates.edit', $template) }}" data-modal-title="Editar huella">Editar</a>
                            @endcan
                            @can('fingerprint-templates.delete')
                                <form class="d-inline" method="POST" action="{{ route('fingerprint-templates.destroy', $template) }}" data-confirm-delete="Eliminar plantilla de huella?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="No hay huellas registradas." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $templates->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
