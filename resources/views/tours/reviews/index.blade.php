@extends('layouts.admin')

@section('title', 'Revision de tours | '.config('app.name', 'Base Admin'))
@section('page-title', 'Revision de tours')
@section('page-subtitle', 'Tours enviados por empresas pendientes de aprobacion')

@section('content')
    <x-ui.table-card title="Pendientes de revision">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Tour</th>
                    <th>Empresa</th>
                    <th>Categoria</th>
                    <th>Enviado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tours as $tour)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $tour->display_title }}</div>
                            <div class="text-body-secondary small">{{ $tour->reference_code }}</div>
                            @if ($tour->correction_history || $tour->rejection_points)
                                <div class="alert alert-warning py-2 px-3 mt-2 mb-0">
                                    <div class="fw-bold text-warning-emphasis">Correcciones solicitadas anteriormente</div>
                                    @foreach (($tour->correction_history ?: [['points' => $tour->rejection_points]]) as $historyIndex => $entry)
                                        <div class="small fw-semibold mt-1">Revision {{ $historyIndex + 1 }}</div>
                                        <ul class="mb-0 ps-3">
                                            @foreach (($entry['points'] ?? []) as $point)
                                                <li>{{ $point }}</li>
                                            @endforeach
                                        </ul>
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>{{ $tour->company?->name ?? '-' }}</td>
                        <td>{{ $tour->category?->name ?? '-' }}</td>
                        <td>{{ $tour->updated_at?->format('Y-m-d H:i') }}</td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('tours.show', $tour) }}" data-modal-url="{{ route('tours.show', $tour) }}" data-modal-title="Detalle de tour">Ver</a>
                            <form class="d-inline" method="POST" action="{{ route('tours.approve', $tour) }}">
                                @csrf
                                <button class="btn btn-success btn-sm" type="submit">Aprobar</button>
                            </form>
                            <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#reject-tour-{{ $tour->id }}">Solicitar correccion</button>
                        </td>
                    </tr>
                    <tr class="collapse" id="reject-tour-{{ $tour->id }}">
                        <td colspan="5">
                            <form method="POST" action="{{ route('tours.reject', $tour) }}" class="border rounded p-3">
                                @csrf
                                <label class="form-label">Correcciones solicitadas para enviar a la empresa</label>
                                <textarea class="form-control mb-2" name="rejection_points[]" rows="3" required placeholder="Describe el punto que debe corregirse"></textarea>
                                <textarea class="form-control mb-2" name="rejection_points[]" rows="3" placeholder="Otro punto de correccion"></textarea>
                                <textarea class="form-control mb-3" name="rejection_points[]" rows="3" placeholder="Otro punto de correccion"></textarea>
                                <div class="text-end">
                                    <button class="btn btn-danger" type="submit">Enviar solicitud de correccion</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="No hay tours pendientes de revision." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $tours->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
