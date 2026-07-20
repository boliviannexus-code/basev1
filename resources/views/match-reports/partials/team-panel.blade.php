<div class="col-xl-6">
    <div class="border rounded bg-body overflow-hidden">
        <div class="d-flex justify-content-between align-items-center gap-2 p-2 {{ $tone }}">
            <h2 class="h2 mb-0">{{ $teamName }}</h2>
            <button class="btn btn-success btn-icon" type="button" data-bs-toggle="modal" data-bs-target="#add-player-{{ $side }}-{{ $report->id }}" title="Agregar jugador" @disabled($options->isEmpty())>
                <i class="ti ti-plus"></i>
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width: 3rem;">#</th>
                        <th>Jugador</th>
                        <th class="text-center" style="width: 7rem;">Goles</th>
                        <th class="text-center" style="width: 7rem;">Amarillas</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($players as $player)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>
                                <div class="fw-semibold">
                                    @if ($player->jersey_number !== null)
                                        <span class="badge text-bg-light border text-body me-1">#{{ $player->jersey_number }}</span>
                                    @endif
                                    {{ $player->player?->full_name ?? '-' }}
                                </div>
                                <div class="text-body-secondary small">{{ $player->player?->internal_code ?? '-' }}</div>
                            </td>
                            @foreach ([
                                'goals' => $player->goals,
                                'yellow_cards' => $player->yellow_cards,
                            ] as $field => $value)
                                <td class="text-center">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <form method="POST" action="{{ route('match-reports.players.stats', $player) }}" data-ajax-form data-refresh-url="{{ $refreshUrl }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="field" value="{{ $field }}">
                                            <input type="hidden" name="delta" value="-1">
                                            <button class="btn btn-danger btn-icon btn-sm" type="submit" @disabled($value <= 0)>
                                                <i class="ti ti-minus"></i>
                                            </button>
                                        </form>
                                        <span class="form-control form-control-sm text-center" style="width: 2.5rem;">{{ $value }}</span>
                                        <form method="POST" action="{{ route('match-reports.players.stats', $player) }}" data-ajax-form data-refresh-url="{{ $refreshUrl }}">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="field" value="{{ $field }}">
                                            <input type="hidden" name="delta" value="1">
                                            <button class="btn btn-success btn-icon btn-sm" type="submit" @disabled($field === 'yellow_cards' && $value >= 2)>
                                                <i class="ti ti-plus"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="5" message="Agrega jugadores que participaron en el partido." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal modal-blur fade" id="add-player-{{ $side }}-{{ $report->id }}" tabindex="-1" aria-labelledby="add-player-title-{{ $side }}-{{ $report->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('match-reports.players.store', $report) }}" data-ajax-form data-refresh-url="{{ $refreshUrl }}">
                @csrf
                <input type="hidden" name="team_side" value="{{ $side }}">
                <div class="modal-header">
                    <h2 class="modal-title" id="add-player-title-{{ $side }}-{{ $report->id }}">Agregar jugador · {{ $teamName }}</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="player-search-{{ $side }}-{{ $report->id }}">Buscar jugador</label>
                        <input class="form-control" id="player-search-{{ $side }}-{{ $report->id }}" type="search" placeholder="Nombre, CI o codigo" data-match-player-search data-target="#player-select-{{ $side }}-{{ $report->id }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="player-select-{{ $side }}-{{ $report->id }}">Jugador habilitado</label>
                        <select class="form-select" id="player-select-{{ $side }}-{{ $report->id }}" name="tournament_team_player_id" required>
                            <option value="">Seleccionar jugador</option>
                            @foreach ($options as $option)
                                @php($playerOption = $option->player)
                                <option value="{{ $option->id }}" data-search="{{ str($playerOption?->full_name.' '.$playerOption?->ci.' '.$playerOption?->internal_code)->lower() }}">
                                    {{ $playerOption?->full_name ?? '-' }} · CI {{ $playerOption?->ci ?? '-' }} · {{ $playerOption?->internal_code ?? '-' }}
                                </option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback" data-error-for="tournament_team_player_id"></div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="jersey-number-{{ $side }}-{{ $report->id }}">Numero con el que jugo</label>
                        <input class="form-control" id="jersey-number-{{ $side }}-{{ $report->id }}" name="jersey_number" type="number" min="0" max="999" placeholder="Ej. 10" required>
                        <div class="invalid-feedback" data-error-for="jersey_number"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">
                        <i class="ti ti-plus me-1"></i>
                        Agregar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
