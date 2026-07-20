<form method="POST" action="{{ route('courts.store') }}" data-ajax-form>
    @csrf
    @include('courts.partials.fields', ['court' => null])
    <div class="d-flex justify-content-end gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('courts.index') }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">Guardar cancha</button>
    </div>
</form>
