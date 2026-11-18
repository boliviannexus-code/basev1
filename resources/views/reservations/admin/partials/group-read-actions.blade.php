<div class="reservation-read-action-list">

                @if ((auth()->user()?->can('reservations.manage') || auth()->user()?->can('occupancy.manage')) && ! in_array($group->status, ['cancelled', 'no_show'], true))
                    <form action="{{ route('admin.reservation-groups.check-in', $group) }}" method="post">
                        @csrf
                        <button class="btn btn-success" type="submit" @disabled(! $canStartCheckIn) title="{{ $checkInAlreadyRegistered ? 'El check-in ya fue registrado' : ($canStartCheckIn ? 'Enviar reserva a check-in' : 'Disponible solo en la fecha de ingreso') }}">
                            <i class="ti ti-login me-1"></i>{{ $checkInAlreadyRegistered ? 'Check-in registrado' : ($group->status === 'checked_in' ? 'Continuar check-in' : 'Check-in') }}
                        </button>
                    </form>
                @endif

                @if ($canEditReservation)
                    <a class="btn btn-outline-primary" href="{{ route('admin.reservation-groups.edit', $group) }}" data-modal-url="{{ route('admin.reservation-groups.edit', $group) }}" data-modal-title="Modificar reserva {{ $group->code }}" data-modal-size="xl">
                        <i class="ti ti-edit me-1"></i>Modificar reserva
                    </a>
                @endif

@can('reservations.manage')
    @if ($canCancelReservation || $canMarkNoShow)

                @if ($canCancelReservation)
                    <form action="{{ route('admin.reservation-groups.cancel', $group) }}" method="post" data-reservation-reason-form data-reason-title="Cancelar reserva">
                        @csrf
                        @method('patch')
                        <input type="hidden" name="reason" value="">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="ti ti-calendar-x me-1"></i>Cancelar reserva
                        </button>
                    </form>
                @endif
                @if ($canMarkNoShow)
                    <form action="{{ route('admin.reservation-groups.no-show', $group) }}" method="post" data-reservation-reason-form data-reason-title="No show">
                        @csrf
                        @method('patch')
                        <input type="hidden" name="reason" value="">
                        <button class="btn btn-outline-danger" type="submit">
                            <i class="ti ti-user-x me-1"></i>No se presentó
                        </button>
                    </form>
                @endif

    @endif
@endcan
</div>
