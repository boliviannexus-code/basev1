<a class="btn btn-outline-secondary btn-sm" href="{{ route('payment-methods.show', $paymentMethod) }}" data-modal-url="{{ route('payment-methods.show', $paymentMethod) }}" data-modal-title="Detalle de metodo">Ver</a>
@if (auth()->user()?->can('payment-methods.update') || auth()->user()?->can('occupancy.manage'))
    <a class="btn btn-outline-primary btn-sm" href="{{ route('payment-methods.edit', $paymentMethod) }}" data-modal-url="{{ route('payment-methods.edit', $paymentMethod) }}" data-modal-title="Editar metodo">Editar</a>
@endif
@can('payment-methods.delete')
    <form class="d-inline" method="POST" action="{{ route('payment-methods.destroy', $paymentMethod) }}" data-confirm-delete="Eliminar metodo de pago?">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
    </form>
@endcan
