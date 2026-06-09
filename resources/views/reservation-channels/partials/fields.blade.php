<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="{{ $prefix }}-name">Nombre</label>
        <input class="form-control @error('name') is-invalid @enderror" id="{{ $prefix }}-name" name="name" value="{{ old('name', $channel->name ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="name">{{ $errors->first('name') }}</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="{{ $prefix }}-type">Tipo</label>
        <select class="form-select @error('type') is-invalid @enderror" id="{{ $prefix }}-type" name="type" required>
            @foreach ($types as $channelType)
                <option value="{{ $channelType }}" @selected(old('type', $channel->type ?? 'direct') === $channelType)>
                    {{ ['direct' => 'Directo / walk-in', 'ota' => 'OTA', 'agency' => 'Agencia', 'corporate' => 'Corporativo', 'other' => 'Otro'][$channelType] }}
                </option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="type">{{ $errors->first('type') }}</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="{{ $prefix }}-slug">Identificador</label>
        <input class="form-control @error('slug') is-invalid @enderror" id="{{ $prefix }}-slug" name="slug" value="{{ old('slug', $channel->slug ?? '') }}" placeholder="Se genera desde el nombre">
        <div class="invalid-feedback" data-error-for="slug">{{ $errors->first('slug') }}</div>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="{{ $prefix }}-commission">Comision %</label>
        <input class="form-control @error('commission_percent') is-invalid @enderror" id="{{ $prefix }}-commission" name="commission_percent" type="number" min="0" max="100" step="0.01" value="{{ old('commission_percent', $channel->commission_percent ?? '') }}">
        <div class="invalid-feedback" data-error-for="commission_percent">{{ $errors->first('commission_percent') }}</div>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="{{ $prefix }}-sort-order">Orden</label>
        <input class="form-control @error('sort_order') is-invalid @enderror" id="{{ $prefix }}-sort-order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $channel->sort_order ?? 0) }}">
        <div class="invalid-feedback" data-error-for="sort_order">{{ $errors->first('sort_order') }}</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $prefix }}-contact-name">Contacto</label>
        <input class="form-control @error('contact_name') is-invalid @enderror" id="{{ $prefix }}-contact-name" name="contact_name" value="{{ old('contact_name', $channel->contact_name ?? '') }}">
        <div class="invalid-feedback" data-error-for="contact_name">{{ $errors->first('contact_name') }}</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $prefix }}-contact-email">Correo</label>
        <input class="form-control @error('contact_email') is-invalid @enderror" id="{{ $prefix }}-contact-email" name="contact_email" type="email" value="{{ old('contact_email', $channel->contact_email ?? '') }}">
        <div class="invalid-feedback" data-error-for="contact_email">{{ $errors->first('contact_email') }}</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $prefix }}-contact-phone">Telefono</label>
        <input class="form-control @error('contact_phone') is-invalid @enderror" id="{{ $prefix }}-contact-phone" name="contact_phone" value="{{ old('contact_phone', $channel->contact_phone ?? '') }}">
        <div class="invalid-feedback" data-error-for="contact_phone">{{ $errors->first('contact_phone') }}</div>
    </div>
    <div class="col-12">
        <label class="form-label" for="{{ $prefix }}-notes">Notas</label>
        <textarea class="form-control @error('notes') is-invalid @enderror" id="{{ $prefix }}-notes" name="notes" rows="2">{{ old('notes', $channel->notes ?? '') }}</textarea>
        <div class="invalid-feedback" data-error-for="notes">{{ $errors->first('notes') }}</div>
    </div>
    <div class="col-12">
        <label class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" name="is_active" type="checkbox" value="1" @checked(old('is_active', $channel->is_active ?? true)) @disabled($channel->is_protected ?? false)>
            <span class="form-check-label">Activo</span>
        </label>
    </div>
</div>
