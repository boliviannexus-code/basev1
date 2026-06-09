@extends('layouts.admin')

@section('title', 'Canales de reserva | '.config('app.name', 'Base Admin'))
@section('page-title', 'Canales de reserva')
@section('page-subtitle', 'Procedencia de reservas para check-in y reportes')

@section('content')
    <div class="row g-3" data-refresh-container>
        <div class="col-lg-4">
            <x-ui.card title="Nuevo canal">
                <div class="card-body">
                    <form method="POST" action="{{ route('reservation-channels.store') }}" data-ajax-form data-refresh-url="{{ route('reservation-channels.index') }}" novalidate>
                        @csrf
                        @include('reservation-channels.partials.fields', ['channel' => $channel, 'prefix' => 'reservation-channel-create'])
                        <button class="btn btn-primary w-100 mt-3" type="submit">
                            <i class="ti ti-plus me-1"></i>Crear canal
                        </button>
                    </form>
                </div>
            </x-ui.card>
        </div>
        <div class="col-lg-8">
            <x-ui.table-card title="Canales registrados">
                <x-slot:actions>
                    <form class="d-inline-flex gap-2" method="get" action="{{ route('reservation-channels.index') }}">
                        <select class="form-select form-select-sm" name="type" onchange="this.form.submit()">
                            <option value="">Todos los tipos</option>
                            @foreach ($types as $channelType)
                                <option value="{{ $channelType }}" @selected($type === $channelType)>
                                    {{ ['direct' => 'Directo / walk-in', 'ota' => 'OTA', 'agency' => 'Agencia', 'corporate' => 'Corporativo', 'other' => 'Otro'][$channelType] }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </x-slot:actions>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Canal</th>
                                <th>Tipo</th>
                                <th>Comision</th>
                                <th>Reservas</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($channels as $item)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $item->name }}</div>
                                        <div class="text-body-secondary small">
                                            {{ $item->slug }}
                                            @if ($item->contact_name)
                                                · {{ $item->contact_name }}
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ ['direct' => 'Directo / walk-in', 'ota' => 'OTA', 'agency' => 'Agencia', 'corporate' => 'Corporativo', 'other' => 'Otro'][$item->type] }}</td>
                                    <td>{{ $item->commission_percent !== null ? $item->commission_percent.'%' : '-' }}</td>
                                    <td>{{ $item->reservations_count }}</td>
                                    <td>
                                        <span class="badge text-bg-{{ $item->is_active ? 'success' : 'secondary' }}">{{ $item->is_active ? 'Activo' : 'Inactivo' }}</span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-outline-primary btn-sm" type="button" data-reservation-channel-edit-toggle="{{ $item->id }}">
                                            <i class="ti ti-edit me-1"></i>Editar
                                        </button>
                                        <form class="d-inline" method="post" action="{{ route('reservation-channels.toggle', $item) }}">
                                            @csrf
                                            @method('patch')
                                            <button class="btn btn-outline-{{ $item->is_active ? 'warning' : 'success' }} btn-sm" type="submit" @disabled($item->is_protected)>
                                                <i class="ti ti-power me-1"></i>{{ $item->is_active ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>
                                        <form class="d-inline" method="post" action="{{ route('reservation-channels.destroy', $item) }}" data-confirm-delete="Eliminar canal?">
                                            @csrf
                                            @method('delete')
                                            <button class="btn btn-outline-danger btn-sm" type="submit" @disabled($item->is_protected || $item->reservations_count > 0)>
                                                <i class="ti ti-trash me-1"></i>Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <tr class="d-none" data-reservation-channel-edit-panel="{{ $item->id }}">
                                    <td colspan="6">
                                        <form method="POST" action="{{ route('reservation-channels.update', $item) }}" data-ajax-form data-refresh-url="{{ route('reservation-channels.index') }}" novalidate>
                                            @csrf
                                            @method('PUT')
                                            @include('reservation-channels.partials.fields', ['channel' => $item, 'prefix' => 'reservation-channel-'.$item->id])
                                            <div class="d-flex justify-content-end gap-2 mt-3">
                                                <button class="btn btn-outline-secondary btn-sm" type="button" data-reservation-channel-edit-toggle="{{ $item->id }}">Cancelar</button>
                                                <button class="btn btn-primary btn-sm" type="submit">Guardar cambios</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row colspan="6" message="No hay canales de reserva registrados." />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-slot:footer>{{ $channels->links() }}</x-slot:footer>
            </x-ui.table-card>
        </div>
    </div>
@endsection
