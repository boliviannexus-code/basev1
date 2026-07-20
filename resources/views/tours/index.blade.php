@extends('layouts.admin')

@section('title', 'Tours | '.config('app.name', 'Base Admin'))
@section('page-title', 'Tours')
@section('page-subtitle', 'Experiencias y paquetes disponibles por empresa')

@section('content')
    <x-ui.table-card title="Listado de tours" data-refresh-container>
        <x-slot:actions>
            @can('tours.review')
                <a class="btn btn-outline-primary btn-sm" href="{{ route('tours.reviews.index') }}">Revision</a>
            @endcan
            @can('tours.create')
                <a class="btn btn-primary btn-sm" href="{{ route('tours.create') }}">Nuevo tour</a>
            @endcan
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Tour</th>
                    <th>Empresa</th>
                    <th>Categoria</th>
                    <th>Ciudad</th>
                    <th>Estado</th>
                    <th>Revision</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tours as $tour)
                    <tr>
                        <td>{{ $tour->reference_code ?: '-' }}</td>
                       <td>
                            <div class="fw-semibold">{{ $tour->display_title ?: 'Borrador sin titulo' }}</div>
                            </td>
                         <td>{{ $tour->company?->name ?? '-' }}</td>
                        <td>{{ $tour->category?->name ?? '-' }}</td>
                        <td>{{ $tour->city ?: '-' }}</td>
                        <td><span class="badge text-bg-{{ $tour->status === \App\Models\Tour::STATUS_ACTIVE ? 'success' : ($tour->status === \App\Models\Tour::STATUS_DRAFT ? 'warning' : 'secondary') }}">{{ $tour->status_label }}</span></td>
                        <td>
                            <span class="badge text-bg-{{ match ($tour->review_status) { \App\Models\Tour::REVIEW_APPROVED => 'success', \App\Models\Tour::REVIEW_PENDING => 'info', \App\Models\Tour::REVIEW_REJECTED => 'danger', default => 'secondary' } }}">{{ $tour->review_status_label }}</span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('tours.show', $tour) }}" data-modal-url="{{ route('tours.show', $tour) }}" data-modal-title="Detalle de tour">Ver</a>
                            @can('tours.edit')
                                @if (in_array($tour->review_status, [\App\Models\Tour::REVIEW_DRAFT, \App\Models\Tour::REVIEW_REJECTED], true))
                                    <a class="btn btn-outline-primary btn-sm" href="{{ route('tours.edit', $tour) }}">{{ $tour->status === \App\Models\Tour::STATUS_DRAFT ? 'Continuar borrador' : 'Editar tour' }}</a>
                                @elseif ($tour->review_status === \App\Models\Tour::REVIEW_APPROVED)
                                    <a class="btn btn-outline-primary btn-sm" href="{{ route('tours.wizard.edit', [$tour, 'step' => 4]) }}">Editar permitido</a>
                                @endif
                            @endcan
                            @can('tours.pricing')
                                @if ($tour->review_status === \App\Models\Tour::REVIEW_APPROVED)
                                    <a class="btn btn-outline-success btn-sm" href="{{ route('tours.pricing.edit', $tour) }}">Precios</a>
                                @endif
                            @endcan
                            @can('tours.edit')
                                @if ($tour->review_status === \App\Models\Tour::REVIEW_APPROVED)
                                    <form class="d-inline" method="POST" action="{{ route('tours.toggle-status', $tour) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-outline-{{ $tour->status === \App\Models\Tour::STATUS_ACTIVE ? 'warning' : 'primary' }} btn-sm" type="submit">
                                            {{ $tour->status === \App\Models\Tour::STATUS_ACTIVE ? 'Deshabilitar' : 'Habilitar' }}
                                        </button>
                                    </form>
                                @endif
                            @endcan
                            @can('tours.delete')
                                <form class="d-inline" method="POST" action="{{ route('tours.destroy', $tour) }}" data-confirm-delete="Eliminar tour?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="8" message="No hay tours registrados." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $tours->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
