<div class="row g-3">
    @if (! ($template ?? null))
        <div class="col-md-12">
            <label class="form-label" for="fingerprint-user">Usuario</label>
            <select class="form-select" id="fingerprint-user" name="user_id" data-tom-select data-placeholder="Seleccionar usuario" data-fingerprint-user-identity required>
                <option value="">Seleccionar usuario</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" data-fingerprint-identity="{{ $user->email }}" @selected((int) old('user_id') === $user->id)>{{ $user->name }} - {{ $user->email }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback" data-error-for="user_id"></div>
        </div>
    @else
        <div class="col-md-12">
            <label class="form-label">Usuario</label>
            <input class="form-control" value="{{ $template->user?->name }} - {{ $template->user?->email }}" data-fingerprint-user-identity disabled>
        </div>
    @endif

    <div class="col-md-6">
        <label class="form-label" for="fingerprint-format">Formato</label>
        <input class="form-control" id="fingerprint-format" name="format" value="{{ old('format', $template->format ?? '') }}" placeholder="ISO, ANSI, WSQ">
        <div class="invalid-feedback" data-error-for="format"></div>
    </div>

    <div class="col-md-12">
        <label class="form-label" for="fingerprint-template-data">Plantilla</label>
        <textarea class="form-control font-monospace" id="fingerprint-template-data" name="template_data" rows="6" data-fingerprint-template required>{{ old('template_data', $template->template_data ?? '') }}</textarea>
        <div class="invalid-feedback" data-error-for="template_data"></div>
    </div>

    <div class="col-md-12">
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-outline-info btn-sm" type="button" data-fingerprint-detect>Detectar lector</button>
            <button class="btn btn-outline-primary btn-sm" type="button" data-fingerprint-capture>Capturar desde lector</button>
            <button class="btn btn-outline-success btn-sm" type="button" data-fingerprint-verify>Verificar huella</button>
            <label class="btn btn-outline-secondary btn-sm mb-0">
                Cargar archivo
                <input class="d-none" type="file" accept=".txt,.json,.dat" data-fingerprint-file>
            </label>
        </div>
        <div class="form-hint mt-2" data-fingerprint-status>La plantilla se almacena protegida del listado y no se muestra en detalles.</div>
    </div>
</div>
