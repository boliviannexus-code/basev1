@isset($companies)
    @if ($companies->count() > 1)
        <div class="mb-3">
            <label class="form-label" for="article-company">Liga deportiva</label>
            <select class="form-select @error('company_id') is-invalid @enderror" id="article-company" name="company_id" required>
                <option value="">Seleccionar liga</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected((int) old('company_id', $article?->company_id) === (int) $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
            @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endif
@endisset

<div class="mb-3">
    <label class="form-label" for="article-number">Articulo nro.</label>
    <input class="form-control @error('number') is-invalid @enderror" id="article-number" name="number" value="{{ old('number', $article?->number) }}" required maxlength="50">
    @error('number')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-0">
    <label class="form-label" for="article-detail">Detalle</label>
    <textarea class="form-control @error('detail') is-invalid @enderror" id="article-detail" name="detail" rows="5" required maxlength="2000">{{ old('detail', $article?->detail) }}</textarea>
    @error('detail')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
