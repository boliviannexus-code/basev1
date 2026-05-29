<dl class="row mb-0">
    <dt class="col-sm-3">Empresa</dt>
    <dd class="col-sm-9">{{ $tour->company?->name ?? '-' }}</dd>

    <dt class="col-sm-3">Nombre</dt>
    <dd class="col-sm-9">{{ $tour->display_title ?: '-' }}</dd>

    <dt class="col-sm-3">Categoria</dt>
    <dd class="col-sm-9">{{ $tour->category?->name ?? '-' }}</dd>

    <dt class="col-sm-3">Codigo</dt>
    <dd class="col-sm-9">{{ $tour->reference_code ?: '-' }}</dd>

    <dt class="col-sm-3">Duracion</dt>
    <dd class="col-sm-9">{{ $tour->duration ?: '-' }}</dd>

    <dt class="col-sm-3">Punto de recogida</dt>
    <dd class="col-sm-9">{{ $tour->meeting_point ?: '-' }}</dd>

    <dt class="col-sm-3">Tipo de actividad</dt>
    <dd class="col-sm-9">{{ ['private' => 'Privada', 'shared' => 'Compartida'][$tour->activity_type] ?? '-' }}</dd>

    <dt class="col-sm-3">Cupos</dt>
    <dd class="col-sm-9">{{ $tour->capacity ?: '-' }}</dd>

    <dt class="col-sm-3">Limite de reserva</dt>
    <dd class="col-sm-9">
        @if ($tour->booking_deadline_value && $tour->booking_deadline_unit)
            {{ $tour->booking_deadline_value }} {{ $tour->booking_deadline_unit === 'hours' ? 'horas antes' : 'dias antes' }}
        @else
            -
        @endif
    </dd>

    <dt class="col-sm-3">Ubicacion</dt>
    <dd class="col-sm-9">{{ collect([$tour->country, $tour->city, $tour->location_text])->filter()->implode(' - ') ?: '-' }}</dd>

    <dt class="col-sm-3">Estado</dt>
    <dd class="col-sm-9"><span class="badge text-bg-{{ $tour->status === \App\Models\Tour::STATUS_ACTIVE ? 'success' : 'secondary' }}">{{ $tour->status_label }}</span></dd>

    <dt class="col-sm-3">Revision</dt>
    <dd class="col-sm-9">{{ $tour->review_status_label }}</dd>

    @if ($tour->rejection_points)
        <dt class="col-sm-3 text-danger fw-bold">Correcciones</dt>
        <dd class="col-sm-9">
            <div class="alert alert-danger border border-danger border-2 mb-0">
                <div class="fw-bold text-danger text-uppercase mb-2">Puntos actuales que debe corregir</div>
                <ul class="mb-0 ps-3 text-danger fw-semibold">
                    @foreach ($tour->rejection_points as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ul>
            </div>
        </dd>
    @endif

    @if ($tour->correction_history)
        <dt class="col-sm-3">Historial de correcciones</dt>
        <dd class="col-sm-9">
            <div class="vstack gap-2">
                @foreach ($tour->correction_history as $historyIndex => $entry)
                    <div class="border rounded p-2">
                        <div class="fw-semibold">Revision {{ $historyIndex + 1 }}</div>
                        <ul class="mb-0 ps-3">
                            @foreach (($entry['points'] ?? []) as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </dd>
    @endif

    <dt class="col-sm-3">Descripcion breve</dt>
    <dd class="col-sm-9">{!! nl2br(e($tour->short_description ?: $tour->description ?: '-')) !!}</dd>

    <dt class="col-sm-3">Descripcion completa</dt>
    <dd class="col-sm-9">{!! nl2br(e($tour->full_description ?: '-')) !!}</dd>

    <dt class="col-sm-3">Keywords</dt>
    <dd class="col-sm-9">
        @forelse ($tour->keywords ?? [] as $keyword)
            <span class="badge text-bg-light me-1">{{ $keyword }}</span>
        @empty
            -
        @endforelse
    </dd>

    <dt class="col-sm-3">Incluye</dt>
    <dd class="col-sm-9">{!! nl2br(e($tour->includes ?: $tour->included ?: '-')) !!}</dd>

    <dt class="col-sm-3">No incluye</dt>
    <dd class="col-sm-9">{!! nl2br(e($tour->excludes ?: $tour->not_included ?: '-')) !!}</dd>

    <dt class="col-sm-3">Requisitos</dt>
    <dd class="col-sm-9">{!! nl2br(e($tour->requirements ?: '-')) !!}</dd>

    <dt class="col-sm-3">Tipo de guia</dt>
    <dd class="col-sm-9">{{ $tour->guideType?->title ?? '-' }}</dd>

    <dt class="col-sm-3">Transporte</dt>
    <dd class="col-sm-9">{{ $tour->includes_transport ? ($tour->transportType?->title ?? 'Si') : 'No' }}</dd>

    <dt class="col-sm-3">Comida</dt>
    <dd class="col-sm-9">{{ $tour->includes_food ? ($tour->food_details ?: 'Si') : 'No' }}</dd>

    <dt class="col-sm-3">Mascotas</dt>
    <dd class="col-sm-9">{{ $tour->pets_allowed ? 'Permitidas' : 'No permitidas' }}</dd>

    <dt class="col-sm-3">Reservas futuras</dt>
    <dd class="col-sm-9">{{ $tour->bookings_enabled ? 'Habilitadas' : 'Deshabilitadas' }}</dd>

    <dt class="col-sm-3">Precios USD</dt>
    <dd class="col-sm-9">
        @forelse ($tour->prices as $price)
            <div>
                {{ $price->title ?: 'Precio' }}:
                {{ $price->min_people }} - {{ $price->max_people ?: 'mas' }} personas,
                ${{ number_format((float) $price->price_usd, 2) }} por persona
            </div>
        @empty
            -
        @endforelse
    </dd>

    <dt class="col-sm-3">Itinerario</dt>
    <dd class="col-sm-9">
        @forelse ($tour->itineraryDays as $day)
            <div class="mb-3">
                <div class="fw-bold">Dia {{ $day->day_number }}: {{ $day->title }}</div>
                @if ($day->summary)
                    <div class="text-body-secondary mb-2">{{ $day->summary }}</div>
                @endif
                <div class="vstack gap-2">
                    @foreach ($day->stops as $stop)
                        <div class="border rounded p-2">
                            <div class="fw-semibold">
                                @if ($stop->activityType?->icon)
                                    <i class="ti {{ $stop->activityType->icon }}"></i>
                                @endif
                                {{ $stop->start_time?->format('H:i') ? $stop->start_time->format('H:i').' - ' : '' }}{{ $stop->title }}
                            </div>
                            <div class="small text-body-secondary">
                                {{ $stop->activityType?->title ?? 'Actividad' }}
                                @if ($stop->location_name)
                                    · {{ $stop->location_name }}
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            -
        @endforelse
    </dd>
</dl>
