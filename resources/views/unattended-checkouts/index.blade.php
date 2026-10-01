@extends('layouts.admin')
@section('title', 'Casos sin atención')
@section('content')
    <div class="mb-4">
        <h1>Casos sin atención</h1>
        <p class="text-muted">Estancias ocupadas con salida vencida y reservas cuya llegada estaba prevista para un día anterior. Cada caso requiere una decisión manual.</p>
    </div>
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">Estancias con salida pendiente</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter">
                <thead><tr><th>Habitación / huésped</th><th>Salida prevista</th><th>Saldo</th><th>Resolver</th></tr></thead>
                <tbody>
                @forelse ($stays as $stay)
                    <tr>
                        <td>
                            <strong>{{ collect([$stay->space?->title ?: $stay->space?->name, $stay->room?->name ?: $stay->room?->title, $stay->bedUnit?->label])->filter()->join(' · ') }}</strong>
                            <div>{{ $stay->holderGuest?->full_name ?: $stay->holderGuest?->name ?: 'Sin huésped' }}</div>
                            @can('occupancy.manage')
                                <a href="{{ route('check-ins.edit', $stay->check_in_group_id) }}">Ver estancia / gestionar cobros</a>
                            @endcan
                        </td>
                        <td>{{ $stay->check_out_date->format('d/m/Y') }}</td>
                        <td>{{ number_format((float) ($stay->accountStatement?->balance ?? 0), 2) }} {{ $stay->currency }}</td>
                        <td>
                            <form method="POST" action="{{ route('unattended-checkouts.resolve', $stay) }}" class="d-flex flex-wrap gap-2" data-unattended-resolution-form>
                                @csrf
                                <input type="hidden" name="expected_check_out_date" value="{{ $stay->check_out_date->toDateString() }}">
                                <button class="btn btn-outline-primary" name="action" value="extend" type="submit">Agregar 1 noche (hasta {{ $stay->check_out_date->copy()->addDay()->format('d/m/Y') }})</button>
                                <button class="btn btn-warning" name="action" value="checkout" type="submit" data-requires-unattended-comment data-confirm-title="Registrar check-out tardío" data-confirm-text="Este check-out cerrara la estancia fuera de fecha." data-confirm-button="Registrar check-out">Registrar check-out tardío</button>
                            </form>
                            @if ((float) ($stay->accountStatement?->balance ?? 0) > 0 && auth()->user()?->hasRole('super_admin') && auth()->user()?->can('occupancy.manage'))
                                <a class="btn btn-outline-warning mt-2" href="{{ route('stays.account', $stay) }}#stay-discount">
                                    <i class="ti ti-discount-2 me-1"></i>Aplicar descuento
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-4">No hay casos sin atención.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $stays->links() }}</div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h2 class="card-title">Reservas sin llegada</h2>
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter">
                <thead>
                    <tr>
                        <th>Reserva / huésped</th>
                        <th>Llegada prevista</th>
                        <th>Estado</th>
                        <th>Saldo</th>
                        <th>Resolver</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($reservationGroups as $group)
                    <tr>
                        <td>
                            <strong>{{ $group->code }}</strong>
                            <div>{{ $group->guest_name ?: 'Sin huésped' }}</div>
                            <div class="text-muted small">
                                {{ $group->reservations->map(fn ($reservation) => collect([$reservation->space?->title ?: $reservation->space?->name, $reservation->room?->name ?: $reservation->room?->title])->filter()->join(' · '))->filter()->join(' / ') ?: 'Sin recurso asignado' }}
                            </div>
                            @if (auth()->user()?->can('reservations.manage') || auth()->user()?->can('occupancy.manage'))
                                <a href="{{ route('admin.reservation-groups.edit', $group) }}">Modificar reserva</a>
                            @endif
                        </td>
                        <td>{{ $group->check_in->format('d/m/Y') }}</td>
                        <td>{{ str_replace('_', ' ', $group->status) }}</td>
                        <td>{{ number_format((float) $group->balance_amount, 2) }} {{ $group->currency }}</td>
                        <td>
                            <form method="POST" action="{{ route('unattended-checkouts.reservation-groups.resolve', $group) }}" class="d-flex flex-wrap gap-2" data-unattended-resolution-form>
                                @csrf
                                <input type="hidden" name="expected_check_in" value="{{ $group->check_in->toDateString() }}">
                                <button class="btn btn-outline-danger" name="action" value="cancel" type="submit" data-requires-unattended-comment data-confirm-title="Cancelar reserva" data-confirm-text="La reserva se cerrara y la disponibilidad quedara liberada." data-confirm-button="Cancelar reserva">Cancelar</button>
                                <button class="btn btn-warning" name="action" value="no_show" type="submit" data-requires-unattended-comment data-confirm-title="Marcar no show" data-confirm-text="La reserva se cerrara como no show y la disponibilidad quedara liberada." data-confirm-button="Marcar no show">No show</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    @if ($reservations->isEmpty())
                        <tr><td colspan="5" class="text-center text-muted py-4">No hay reservas sin llegada.</td></tr>
                    @endif
                @endforelse

                @foreach ($reservations as $reservation)
                    <tr>
                        <td>
                            <strong>{{ $reservation->code }}</strong>
                            <div>{{ $reservation->guest_name ?: 'Sin huésped' }}</div>
                            <div class="text-muted small">
                                {{ collect([$reservation->space?->title ?: $reservation->space?->name, $reservation->room?->name ?: $reservation->room?->title])->filter()->join(' · ') ?: 'Sin recurso asignado' }}
                            </div>
                            @if (auth()->user()?->can('reservations.view'))
                                <a href="{{ route('admin.reservations.show', $reservation) }}">Ver reserva</a>
                            @endif
                        </td>
                        <td>{{ $reservation->check_in->format('d/m/Y') }}</td>
                        <td>{{ str_replace('_', ' ', $reservation->status) }}</td>
                        <td>{{ number_format((float) $reservation->balance_amount, 2) }} {{ $reservation->currency }}</td>
                        <td>
                            <form method="POST" action="{{ route('unattended-checkouts.reservations.resolve', $reservation) }}" class="d-flex flex-wrap gap-2" data-unattended-resolution-form>
                                @csrf
                                <input type="hidden" name="expected_check_in" value="{{ $reservation->check_in->toDateString() }}">
                                <button class="btn btn-outline-danger" name="action" value="cancel" type="submit" data-requires-unattended-comment data-confirm-title="Cancelar reserva" data-confirm-text="La reserva se cerrara y la disponibilidad quedara liberada." data-confirm-button="Cancelar reserva">Cancelar</button>
                                <button class="btn btn-warning" name="action" value="no_show" type="submit" data-requires-unattended-comment data-confirm-title="Marcar no show" data-confirm-text="La reserva se cerrara como no show y la disponibilidad quedara liberada." data-confirm-button="Marcar no show">No show</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
