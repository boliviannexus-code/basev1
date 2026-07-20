@extends('layouts.admin')

@section('title', 'Nuevo castigo | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nuevo castigo')
@section('page-subtitle', 'Sancion independiente de jornadas')

@section('content')
    @if ($articles->isEmpty())
        <div class="alert alert-warning">Registra al menos un articulo de sancion antes de crear castigos.</div>
    @endif

    <form class="card" method="POST" action="{{ route('punishments.store') }}">
        @csrf
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="punishment-team">Equipo</label>
                <select
                    class="form-select @error('team_id') is-invalid @enderror"
                    id="punishment-team"
                    name="team_id"
                    data-tom-select
                    data-punishment-team
                    data-placeholder="Buscar equipo"
                    required
                >
                    <option value="">Seleccionar equipo</option>
                    @foreach ($teams as $team)
                        <option value="{{ $team->id }}" @selected((int) old('team_id') === (int) $team->id)>{{ $team->name }}</option>
                    @endforeach
                </select>
                @error('team_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="punishment-player">Jugador</label>
                <select
                    class="form-select @error('player_id') is-invalid @enderror"
                    id="punishment-player"
                    name="player_id"
                    data-tom-select
                    data-punishment-player
                    data-url-template="{{ route('punishments.teams.players', ['team' => '__TEAM__']) }}"
                    data-selected-value="{{ old('player_id') }}"
                    data-placeholder="Buscar jugador del equipo"
                    required
                    disabled
                >
                    <option value="">Seleccionar jugador</option>
                </select>
                @error('player_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="punishment-article">Articulo</label>
                <select class="form-select @error('red_card_article_id') is-invalid @enderror" id="punishment-article" name="red_card_article_id" data-tom-select data-placeholder="Buscar articulo" required @disabled($articles->isEmpty())>
                    <option value="">Seleccionar articulo</option>
                    @foreach ($articles as $article)
                        <option value="{{ $article->id }}" @selected((int) old('red_card_article_id') === (int) $article->id)>{{ $article->number }} · {{ str($article->detail)->limit(100) }}</option>
                    @endforeach
                </select>
                @error('red_card_article_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="row g-2">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="punishment-duration-type">Tipo de duracion</label>
                    <select class="form-select @error('duration_type') is-invalid @enderror" id="punishment-duration-type" name="duration_type" required>
                        <option value="months" @selected(old('duration_type') === 'months')>Meses</option>
                        <option value="years" @selected(old('duration_type') === 'years')>Anos</option>
                        <option value="indefinite" @selected(old('duration_type') === 'indefinite')>Indefinido</option>
                    </select>
                    @error('duration_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="punishment-duration-value">Duracion</label>
                    <input class="form-control @error('duration_value') is-invalid @enderror" id="punishment-duration-value" name="duration_value" type="number" min="1" max="100" value="{{ old('duration_value') }}">
                    @error('duration_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="punishment-starts-on">Inicio</label>
                    <input class="form-control @error('starts_on') is-invalid @enderror" id="punishment-starts-on" name="starts_on" type="date" value="{{ old('starts_on', now()->toDateString()) }}" required>
                    @error('starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="mb-0">
                <label class="form-label" for="punishment-reason">Motivo del castigo</label>
                <textarea class="form-control @error('reason') is-invalid @enderror" id="punishment-reason" name="reason" rows="5" required maxlength="2000">{{ old('reason') }}</textarea>
                @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="card-footer text-end">
            <a class="btn btn-outline-secondary" href="{{ route('punishments.index') }}">Cancelar</a>
            <button class="btn btn-primary" type="submit" @disabled($articles->isEmpty())>Guardar</button>
        </div>
    </form>
@endsection
