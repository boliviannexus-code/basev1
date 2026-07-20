<div class="col-xl-6">
    <form class="card" method="POST" action="{{ route('red-cards.matches.store', $match) }}">
        @csrf
        <input type="hidden" name="team_side" value="{{ $side }}">
        <div class="card-header">
            <h2 class="card-title">{{ $teamName }}</h2>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label" for="player-{{ $side }}">Jugador habilitado</label>
                <select class="form-select" id="player-{{ $side }}" name="tournament_team_player_id" required @disabled($options->isEmpty() || $articles->isEmpty())>
                    <option value="">Seleccionar jugador</option>
                    @foreach ($options as $option)
                        @php($playerOption = $option->player)
                        <option value="{{ $option->id }}">{{ $playerOption?->full_name ?? '-' }} · CI {{ $playerOption?->ci ?? '-' }} · {{ $playerOption?->internal_code ?? '-' }}</option>
                    @endforeach
                </select>
            </div>
            <div class="row g-2">
                <div class="col-md-4 mb-3">
                    <label class="form-label" for="number-{{ $side }}">Numero</label>
                    <input class="form-control" id="number-{{ $side }}" name="jersey_number" type="number" min="0" max="999" required @disabled($articles->isEmpty())>
                </div>
                <div class="col-md-8 mb-3">
                    <label class="form-label" for="article-{{ $side }}">Articulo</label>
                    <select class="form-select" id="article-{{ $side }}" name="red_card_article_id" required @disabled($articles->isEmpty())>
                        <option value="">Seleccionar articulo</option>
                        @foreach ($articles as $article)
                            <option value="{{ $article->id }}">{{ $article->number }} · {{ str($article->detail)->limit(70) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="detail-{{ $side }}">Detalle de la accion</label>
                <textarea class="form-control" id="detail-{{ $side }}" name="action_detail" rows="3" required maxlength="2000" @disabled($articles->isEmpty())></textarea>
            </div>
            <div class="mb-0">
                <label class="form-label" for="matches-{{ $side }}">Cantidad de partidos castigados</label>
                <input class="form-control" id="matches-{{ $side }}" name="suspended_matches" type="number" min="0" max="999" required @disabled($articles->isEmpty())>
            </div>
        </div>
        <div class="card-footer text-end">
            @can('red-cards.update')
                <button class="btn btn-danger" type="submit" @disabled($options->isEmpty() || $articles->isEmpty())>Registrar roja</button>
            @endcan
        </div>
    </form>
</div>
