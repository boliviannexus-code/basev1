@extends('layouts.admin')

@section('title', 'Aprobaciones de equipos | '.config('app.name', 'Base Admin'))
@section('page-title', 'Aprobaciones de equipos')
@section('page-subtitle', 'Cambios pendientes de revision por superadmin')

@section('content')
    <x-ui.table-card title="Solicitudes pendientes">
        <x-slot:actions>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('teams.index') }}">Volver a equipos</a>
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Equipo</th>
                    <th>Solicitado por</th>
                    <th>Cambios</th>
                    <th class="text-end">Revision</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $request)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $request->team?->name }}</div>
                            <div class="text-body-secondary small">{{ $request->team?->company?->name ?? '-' }}</div>
                        </td>
                        <td>
                            <div>{{ $request->requester?->name ?? '-' }}</div>
                            <div class="text-body-secondary small">{{ $request->created_at?->format('Y-m-d H:i') }}</div>
                        </td>
                        <td>
                            <dl class="row mb-0 small">
                                <dt class="col-sm-4">Nombre</dt>
                                <dd class="col-sm-8">{{ $request->original_data['name'] ?? '-' }} -> {{ $request->proposed_data['name'] ?? '-' }}</dd>
                                <dt class="col-sm-4">Fundacion</dt>
                                <dd class="col-sm-8">{{ $request->original_data['founded_at'] ?? '-' }} -> {{ $request->proposed_data['founded_at'] ?? '-' }}</dd>
                                <dt class="col-sm-4">Estado</dt>
                                <dd class="col-sm-8">{{ ($request->original_data['is_active'] ?? false) ? 'Activo' : 'Inactivo' }} -> {{ ($request->proposed_data['is_active'] ?? false) ? 'Activo' : 'Inactivo' }}</dd>
                                <dt class="col-sm-4">Notas</dt>
                                <dd class="col-sm-8">{{ $request->proposed_data['notes'] ?? '-' }}</dd>
                            </dl>
                        </td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('teams.approvals.review', $request) }}" class="d-flex flex-column gap-2">
                                @csrf
                                @method('PATCH')
                                <textarea class="form-control form-control-sm" name="review_notes" rows="2" placeholder="Nota opcional"></textarea>
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="btn btn-outline-danger btn-sm" name="decision" value="reject" type="submit">Rechazar</button>
                                    <button class="btn btn-success btn-sm" name="decision" value="approve" type="submit">Aprobar</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="4" message="No hay solicitudes pendientes." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $requests->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
