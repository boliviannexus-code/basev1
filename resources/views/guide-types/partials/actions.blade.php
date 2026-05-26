<a class="btn btn-outline-secondary btn-sm" href="{{ route('guide-types.show', $guideType) }}" data-modal-url="{{ route('guide-types.show', $guideType) }}" data-modal-title="Detalle de tipo de guia">Ver</a>
@can('guide_types.update')
    <a class="btn btn-outline-primary btn-sm" href="{{ route('guide-types.edit', $guideType) }}" data-modal-url="{{ route('guide-types.edit', $guideType) }}" data-modal-title="Editar tipo de guia">Editar</a>
@endcan
@can('guide_types.delete')
    <form class="d-inline" method="POST" action="{{ route('guide-types.destroy', $guideType) }}" data-confirm-delete="Eliminar tipo de guia?">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
    </form>
@endcan
