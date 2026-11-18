    @can('reservations.manage')
<div class="reservation-read-action-list">
                @if (in_array($reservation->status, ['pending_payment', 'payment_under_review'], true))
                    <form action="{{ route('admin.reservations.approve', $reservation->id) }}" method="post">
                        @csrf
                        @method('patch')
                        <button class="btn btn-success" type="submit">
                            <i class="ti ti-check me-1"></i>Aprobar pago y confirmar
                        </button>
                    </form>

                    <form action="{{ route('admin.reservations.reject', $reservation->id) }}" method="post" data-reservation-reason-form data-reason-title="Rechazar y liberar">
                        @csrf
                        @method('patch')
                        <input type="hidden" name="reason" value="">
                        <button class="btn btn-outline-danger" type="submit">
                            <i class="ti ti-x me-1"></i>Rechazar y liberar
                        </button>
                    </form>
                @endif

                @if ($canCancelReservation)
                    <form action="{{ route('admin.reservations.cancel', $reservation->id) }}" method="post" data-reservation-reason-form data-reason-title="Cancelar reserva">
                        @csrf
                        @method('patch')
                        <input type="hidden" name="reason" value="">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="ti ti-calendar-x me-1"></i>Cancelar reserva
                        </button>
                    </form>
                @endif
                @if ($canMarkNoShow)
                    <form action="{{ route('admin.reservations.no-show', $reservation->id) }}" method="post" data-reservation-reason-form data-reason-title="No show">
                        @csrf
                        @method('patch')
                        <input type="hidden" name="reason" value="">
                        <button class="btn btn-outline-danger" type="submit">
                            <i class="ti ti-user-x me-1"></i>No se presentó
                        </button>
                    </form>
                @endif
</div>
    @endcan
