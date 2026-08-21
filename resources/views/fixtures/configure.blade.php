@extends('layouts.admin')

@section('title', 'Configurar fixture | '.config('app.name', 'Base Admin'))
@section('page-title', 'Configurar fixture')
@section('page-subtitle', $tournament->name.' · '.$category->name)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('fixtures.series', ['tournament' => $tournament, 'category' => $category]) }}">
            <i class="ti ti-arrow-left me-1"></i>
            Series
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="text-body-secondary small">{{ $seriesCount }} series · {{ $totalTeams }} equipos</span>
            @if ($activeGeneration)
                <a class="btn btn-outline-primary btn-sm" href="{{ route('fixtures.report', $activeGeneration) }}">
                    <i class="ti ti-list-details me-1"></i>
                    Ver fixture generado
                </a>
            @endif
        </div>
    </div>

    @if (! $activeGeneration)
        <x-ui.table-card title="Generar primera fase">
            <div class="alert alert-info">
                Puedes iniciar el campeonato ahora. La clasificacion y la modalidad de segunda fase se definiran por separado cuando las conozcas.
            </div>
            <form method="POST" action="{{ route('fixtures.generate', ['tournament' => $tournament, 'category' => $category]) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="fixture-option">
                            <input class="form-check-input" type="radio" name="first_phase_rounds" value="1" @checked(old('first_phase_rounds', 1) == 1)>
                            <span><span class="fixture-option-title">Solo ida</span><span class="fixture-option-copy">Cada equipo juega una vez contra cada rival de su serie.</span></span>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="fixture-option">
                            <input class="form-check-input" type="radio" name="first_phase_rounds" value="2" @checked(old('first_phase_rounds') == 2)>
                            <span><span class="fixture-option-title">Ida y vuelta</span><span class="fixture-option-copy">Cada cruce se repite invirtiendo localia.</span></span>
                        </label>
                    </div>
                </div>
                <button class="btn btn-success mt-3" type="submit"><i class="ti ti-calendar-plus me-1"></i>Generar y guardar primera fase</button>
            </form>
        </x-ui.table-card>
    @elseif (($activeGeneration->config['second_phase_status'] ?? null) === 'configured' || array_key_exists('second_phase_mode', $activeGeneration->config))
        <x-ui.table-card title="Fases del campeonato">
            <div class="alert alert-success mb-0">
                La primera y segunda fase ya estan definidas. Puedes consultar todos los partidos desde el fixture generado.
            </div>
        </x-ui.table-card>
    @else
    <x-ui.table-card title="Definir clasificacion y segunda fase">
        <div class="alert alert-info py-2 mb-3">
            La primera fase ya esta guardada. Esta configuracion agregara la clasificacion y la segunda fase sin modificar los partidos existentes.
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <span class="badge text-bg-primary">{{ $seriesCount }} series</span>
            <span class="badge text-bg-secondary">{{ $totalTeams }} equipos</span>
            @foreach ($series as $serie)
                <span class="badge text-bg-light border text-body">{{ $serie['label'] }}: {{ $serie['team_count'] }} equipos</span>
            @endforeach
        </div>

        <div class="fixture-stepper" data-fixture-stepper data-series-count="{{ $seriesCount }}" data-total-teams="{{ $totalTeams }}" data-min-team-count="{{ $minTeamCount }}" data-first-phase-label="{{ ((int) ($activeGeneration->config['first_phase_rounds'] ?? 1)) === 2 ? 'Ida y vuelta' : 'Solo ida' }}">
            <div class="fixture-stepper-header" role="list" aria-label="Pasos del fixture">
                <button class="fixture-step-indicator active" type="button" data-fixture-step-indicator="1">1. Primera fase guardada</button>
                <button class="fixture-step-indicator" type="button" data-fixture-step-indicator="2">2. Clasificacion</button>
                <button class="fixture-step-indicator" type="button" data-fixture-step-indicator="3">3. Segunda fase</button>
                <button class="fixture-step-indicator" type="button" data-fixture-step-indicator="4">4. Resumen</button>
            </div>

            <form class="fixture-stepper-body" method="POST" action="{{ route('fixtures.second-phase.generate', $activeGeneration) }}" autocomplete="off">
                @csrf
                <section data-fixture-step="1">
                    <div class="alert alert-success mb-0">
                        Primera fase guardada: <strong>{{ ((int) ($activeGeneration->config['first_phase_rounds'] ?? 1)) === 2 ? 'ida y vuelta' : 'solo ida' }}</strong>, con {{ $activeGeneration->matches_count }} partido(s). Ya puedes programarlos mientras defines el resto del campeonato.
                    </div>
                </section>

                <section class="d-none" data-fixture-step="2">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="fixture-qualifiers">
                                {{ $seriesCount > 1 ? 'Clasificados por serie' : 'Clasificados a segunda fase' }}
                            </label>
                            <input class="form-control" id="fixture-qualifiers" name="qualifiers_per_series" type="number" min="0" max="{{ $minTeamCount }}" value="{{ $defaultQualifiersPerSeries }}" data-fixture-qualifiers-input>
                            <div class="form-hint">
                                @if ($seriesCount > 1)
                                    Ejemplo: 2 por serie clasifica 2 de Serie A, 2 de Serie B, etc.
                                @else
                                    En serie unica se toma directamente el numero seleccionado.
                                @endif
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total clasificado calculado</label>
                            <div class="form-control-plaintext fw-semibold" data-fixture-qualified-count>-</div>
                            <div class="form-hint" data-fixture-qualified-help>-</div>
                        </div>
                        <div class="col-md-12">
                            <div class="alert alert-light border mb-0" data-fixture-bracket-advice>
                                Selecciona cuantos equipos clasifican para calcular la fase recomendada.
                            </div>
                        </div>
                        <div class="col-md-12 d-none" data-fixture-fill-options>
                            <label class="form-label" for="fixture-fill-rule">Criterio para completar llaves</label>
                            <select class="form-select" id="fixture-fill-rule" name="fill_rule" data-fixture-fill-rule>
                                <option value="best_thirds">Mejor tercero o mejores terceros</option>
                            </select>
                            <div class="form-hint" data-fixture-fill-help></div>
                        </div>
                    </div>
                </section>

                <section class="d-none" data-fixture-step="3">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="fixture-option">
                                <input class="form-check-input" type="radio" name="second_phase_mode" value="knockout" @checked(true)>
                                <span>
                                    <span class="fixture-option-title">A. Llaves</span>
                                    <span class="fixture-option-copy">Octavos, cuartos, semifinal o final segun clasificados.</span>
                                </span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="fixture-option">
                                <input class="form-check-input" type="radio" name="second_phase_mode" value="league">
                                <span>
                                    <span class="fixture-option-title">B. Liguilla</span>
                                    <span class="fixture-option-copy">Los clasificados juegan una mini liga.</span>
                                </span>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="fixture-option">
                                <input class="form-check-input" type="radio" name="second_phase_mode" value="accumulative">
                                <span>
                                    <span class="fixture-option-title">C. Acumulativo</span>
                                    <span class="fixture-option-copy">El campeonato termina con la primera fase por puntos.</span>
                                </span>
                            </label>
                        </div>
                        <div class="col-md-12" data-fixture-knockout-panel>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Ronda inicial sugerida</label>
                                    <div class="form-control-plaintext fw-semibold" data-fixture-knockout-round>-</div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Llaves por etapa</label>
                                    <div class="fixture-bracket-stages" data-fixture-stage-list></div>
                                    <div class="form-hint">Cada etapa tiene su propia modalidad: partido unico o ida y vuelta.</div>
                                </div>
                                <div class="col-md-6 d-none" data-fixture-third-place-panel>
                                    <label class="form-label" for="fixture-third-place">Partido por tercer lugar</label>
                                    <select class="form-select" id="fixture-third-place" name="third_place">
                                        <option value="0">No jugar tercer lugar</option>
                                        <option value="1">Jugar tercer lugar entre perdedores de semifinal</option>
                                    </select>
                                    <div class="form-hint">Esta opcion solo aplica cuando la llave llega a semifinal.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 d-none" data-fixture-league-panel>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="fixture-league-rounds">Ruedas de liguilla</label>
                                    <select class="form-select" id="fixture-league-rounds" name="league_rounds">
                                        <option value="1">Solo ida</option>
                                        <option value="2">Ida y vuelta</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="fixture-league-champion">Definicion del campeon</label>
                                    <select class="form-select" id="fixture-league-champion" name="league_champion_rule">
                                        <option value="table">Tabla acumulada de la liguilla</option>
                                        <option value="top_two_final">Final entre los 2 primeros</option>
                                        <option value="semifinal_final">Semifinal y final</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12 d-none" data-fixture-accumulative-panel>
                            <div class="alert alert-info mb-0">
                                No habra segunda fase. El campeon se define por puntos acumulados de la primera fase.
                            </div>
                        </div>
                    </div>
                </section>

                <section class="d-none" data-fixture-step="4">
                    <div class="fixture-summary-header">
                        <div>
                            <span class="text-body-secondary d-block small">Resumen del campeonato</span>
                            <strong>{{ $tournament->name }} · {{ $category->name }}</strong>
                        </div>
                        <span class="badge text-bg-primary">{{ $seriesCount }} series · {{ $totalTeams }} equipos</span>
                    </div>

                    <div class="fixture-summary-flow">
                        <div class="fixture-summary-step">
                            <span class="fixture-summary-number">1</span>
                            <div>
                                <span class="fixture-summary-label">Primera fase</span>
                                <strong data-fixture-summary="first-phase">-</strong>
                                <small>Los equipos compiten dentro de su serie.</small>
                            </div>
                        </div>

                        <div class="fixture-summary-step">
                            <span class="fixture-summary-number">2</span>
                            <div>
                                <span class="fixture-summary-label">Clasificacion</span>
                                <strong data-fixture-summary="qualifiers">{{ $defaultQualifiersPerSeries }} por serie</strong>
                                <small>Equipos que avanzan despues de la primera fase.</small>
                            </div>
                        </div>

                        <div class="fixture-summary-step">
                            <span class="fixture-summary-number">3</span>
                            <div>
                                <span class="fixture-summary-label">Segunda fase</span>
                                <strong data-fixture-summary="second-phase">-</strong>
                                <small>Formato que definira las instancias finales.</small>
                            </div>
                        </div>

                        <div class="fixture-summary-step">
                            <span class="fixture-summary-number">4</span>
                            <div>
                                <span class="fixture-summary-label">Campeon</span>
                                <strong data-fixture-summary="champion">-</strong>
                                <small>Forma en que se definira al ganador del campeonato.</small>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-3 mb-0">
                        Se generaran partidos independientes y quedaran pendientes de programacion. Las llaves futuras usaran semillas que luego se resolveran desde la tabla de posiciones.
                    </div>
                </section>

                <div class="fixture-stepper-actions">
                    <button class="btn btn-outline-secondary" type="button" data-fixture-prev-step disabled>
                        <i class="ti ti-arrow-left me-1"></i>
                        Anterior
                    </button>
                    <button class="btn btn-primary" type="button" data-fixture-next-step>
                        Siguiente
                        <i class="ti ti-arrow-right ms-1"></i>
                    </button>
                    <button class="btn btn-success d-none" type="submit" data-fixture-submit>
                        <i class="ti ti-calendar-plus me-1"></i>
                        Guardar y generar segunda fase
                    </button>
                </div>
            </form>
        </div>
    </x-ui.table-card>
    @endif
@endsection
