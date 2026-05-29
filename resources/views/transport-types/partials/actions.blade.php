<a class="btn btn-outline-secondary btn-sm" href="{{ route('transport-types.show', $transportType) }}" data-modal-url="{{ route('transport-types.show', $transportType) }}" data-modal-title="Detalle de tipo de transporte">Ver</a>
@can('transport_types.update')
    <a class="btn btn-outline-primary btn-sm" href="{{ route('transport-types.edit', $transportType) }}" data-modal-url="{{ route('transport-types.edit', $transportType) }}" data-modal-title="Editar tipo de transporte">Editar</a>
@endcan
@can('transport_types.delete')
    <form class="d-inline" method="POST" action="{{ route('transport-types.destroy', $transportType) }}" data-confirm-delete="Eliminar tipo de transporte?">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
    </form>
@endcan
