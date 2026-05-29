<form method="POST" action="{{ route('players.update', $player) }}" data-ajax-form data-refresh-url="{{ route('players.index') }}" novalidate>
    @csrf
    @method('PUT')
    @include('players.partials.fields', compact('player'))
    <div class="mt-4 d-flex justify-content-end gap-2">
        <button class="btn btn-primary" type="submit"><span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>Guardar cambios</button>
    </div>
</form>
