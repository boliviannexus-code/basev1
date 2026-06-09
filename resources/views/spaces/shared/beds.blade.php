@extends('layouts.admin')

@section('title', 'Camas | '.config('app.name', 'Base Admin'))
@section('page-title', $space->name ?: 'Alojamiento compartido')
@section('page-subtitle', 'Camas por habitacion')

@section('content')
    <div data-refresh-container>
    @include('spaces.shared.partials.stepper')
    <div class="shared-bed-grid">
        @foreach ($space->rooms as $room)
            @php
                $bedCount = $room->beds->sum('quantity');
                $capacity = $room->max_capacity ?? 0;
            @endphp
            <section class="shared-bed-card">
                <div class="shared-bed-card-header">
                    <div class="min-w-0">
                        <h3>Hab {{ $room->title ?: $room->name }}</h3>
                        {{-- <div class="text-body-secondary small">{{ $room->name ?: $room->room_number ?: 'Habitacion' }}</div> --}}
                    </div>
                    <div class="shared-bed-stats">
                        <span title="Camas"><i class="ti ti-bed"></i>{{ $bedCount }}</span>
                        <span title="Capacidad"><i class="ti ti-users"></i>{{ $capacity }}</span>
                    </div>
                </div>

                <form class="shared-bed-form" method="POST" action="{{ route('spaces.shared.beds.store', [$space, $room]) }}" data-ajax-form novalidate>
                    @csrf
                    <select class="form-select form-select-sm" name="bed_type_id" aria-label="Tipo de cama" required>
                        <option value="">Tipo de cama</option>
                        @foreach ($bedTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }} · {{ $type->capacity }}</option>
                        @endforeach
                    </select>
                    <input class="form-control form-control-sm shared-bed-quantity" name="quantity" type="number" min="1" value="1" aria-label="Cantidad" required>
                    <button class="btn btn-primary btn-sm shared-bed-add" type="submit" title="Agregar cama">
                        <i class="ti ti-plus"></i>
                    </button>
                </form>

                <div class="shared-bed-list">
                    @forelse ($room->beds as $bed)
                        <div class="shared-bed-row">
                            <div class="shared-bed-main">
                                <strong>{{ $bed->bedType?->name }} {{ $bed->quantity  }}</strong>
                                {{-- <span>{{ $bed->quantity }} x {{ $bed->capacity_per_bed }} = {{ $bed->total_capacity }}</span> --}}
                            </div>
                            <form method="POST" action="{{ route('spaces.shared.beds.destroy', [$space, $room, $bed]) }}" data-ajax-form data-confirm-delete="Quitar cama?">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-outline-danger btn-icon btn-sm" type="submit" title="Quitar cama" aria-label="Quitar cama">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="shared-bed-empty">Sin camas registradas.</div>
                    @endforelse
                </div>

                @if ($room->bedUnits->isNotEmpty())
                    <div class="mt-3">
                        <div class="text-body-secondary small mb-2">Unidades fisicas visibles en ocupabilidad</div>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach ($room->bedUnits as $unit)
                                <span class="badge text-bg-light">{{ $unit->label }} · {{ $unit->bedType?->name ?: 'Cama' }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
    </div>
    <div class="d-flex justify-content-between mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('spaces.shared.rooms.edit', $space) }}">Volver</a>
        <a class="btn btn-primary" href="{{ route('spaces.shared.room-services.edit', $space) }}">Continuar</a>
    </div>
    </div>
@endsection
Termin
