<a class="btn btn-outline-secondary btn-sm" href="{{ route('activity-types.show', $activityType) }}" data-modal-url="{{ route('activity-types.show', $activityType) }}" data-modal-title="Detalle de tipo de actividad">Ver</a>
@can('activity_types.update')
    <a class="btn btn-outline-primary btn-sm" href="{{ route('activity-types.edit', $activityType) }}" data-modal-url="{{ route('activity-types.edit', $activityType) }}" data-modal-title="Editar tipo de actividad">Editar</a>
@endcan
@can('activity_types.delete')
    <form class="d-inline" method="POST" action="{{ route('activity-types.destroy', $activityType) }}" data-confirm-delete="Eliminar tipo de actividad?">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
    </form>
@endcan
