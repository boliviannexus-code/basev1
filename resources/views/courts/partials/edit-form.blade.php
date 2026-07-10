<form method="POST" action="{{ route('courts.update', $court) }}" data-ajax-form>
    @csrf
    @method('PUT')
    @include('courts.partials.fields', ['court' => $court])
    <div class="d-flex justify-content-end gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('courts.index') }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">Actualizar cancha</button>
    </div>
</form>
