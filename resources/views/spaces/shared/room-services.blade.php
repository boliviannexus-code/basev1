@extends('layouts.admin')

@section('title', 'Servicios de habitacion | '.config('app.name', 'Base Admin'))
@section('page-title', $space->name ?: 'Alojamiento compartido')
@section('page-subtitle', 'Servicios individuales')

@section('content')
    <div data-refresh-container>
    @include('spaces.shared.partials.stepper')
    <div class="room-service-grid">
        @foreach ($space->rooms as $room)
            <section class="room-service-card">
                <div class="room-service-card-header">
                    <div class="min-w-0">
                        <h3>{{ $room->title ?: $room->name }}</h3>
                        <div class="text-body-secondary small">{{ $room->roomServices->count() }} servicio{{ $room->roomServices->count() === 1 ? '' : 's' }}</div>
                    </div>
                    <i class="ti ti-tools room-service-card-icon"></i>
                </div>
                <div class="room-service-card-body">
                    @php($targetRooms = $space->rooms->reject(fn ($targetRoom) => (int) $targetRoom->id === (int) $room->id))
                    @if ($targetRooms->isNotEmpty())
                        <form class="room-service-copy-bar mb-2" method="POST" action="{{ route('spaces.shared.room-services.copy', [$space, $room]) }}" data-ajax-form novalidate>
                            @csrf
                            <label class="form-label mb-0" for="copy-room-services-{{ $room->id }}">Copiar</label>
                            <select class="form-select form-select-sm" id="copy-room-services-{{ $room->id }}" name="target_room_ids[]" multiple data-tom-select data-placeholder="Destino" required>
                                @foreach ($targetRooms as $targetRoom)
                                    <option value="{{ $targetRoom->id }}">{{ $targetRoom->title ?: $targetRoom->name }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-room-services-copy-all title="Seleccionar todas las demas habitaciones">
                                Todas
                            </button>
                            <button class="btn btn-outline-primary btn-icon btn-sm" type="submit" title="Aplicar copia" aria-label="Aplicar copia">
                                <i class="ti ti-copy"></i>
                            </button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('spaces.shared.room-services.store', [$space, $room]) }}" data-ajax-form>
                        @csrf
                        @method('PUT')
                        @php($selected = $room->roomServices->pluck('id'))
                        <div class="room-service-check-grid">
                            @foreach ($roomServices as $service)
                                <label class="room-service-check">
                                    <input class="form-check-input" name="room_services[]" type="checkbox" value="{{ $service->id }}" @checked($selected->contains($service->id))>
                                    <span>{{ $service->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <button class="btn btn-primary btn-sm mt-2" type="submit">
                            <i class="ti ti-device-floppy me-1"></i>Guardar
                        </button>
                    </form>
                </div>
            </section>
        @endforeach
    </div>
    <div class="d-flex justify-content-between mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('spaces.shared.beds.edit', $space) }}">Volver</a>
        <a class="btn btn-primary" href="{{ route('spaces.shared.photos.edit', $space) }}">Continuar</a>
    </div>
    </div>
@endsection
