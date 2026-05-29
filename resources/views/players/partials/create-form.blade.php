<form method="POST" action="{{ route('players.store') }}" data-ajax-form data-refresh-url="{{ route('players.index') }}" novalidate>
    @csrf
    @include('players.partials.fields', ['player' => null])
    <div class="mt-4 d-flex justify-content-end gap-2">
        <button class="btn btn-primary" type="submit"><span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>Crear jugador</button>
    </div>
</form>
