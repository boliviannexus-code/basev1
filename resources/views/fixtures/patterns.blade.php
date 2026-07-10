@extends('layouts.admin')

@section('title', 'Plantillas fixture | '.config('app.name', 'Base Admin'))
@section('page-title', 'Plantillas de fixture')
@section('page-subtitle', 'Combinaciones publicas de 3 a 12 equipos')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('fixtures.index') }}">
            <i class="ti ti-arrow-left me-1"></i>
            Fixture
        </a>
        <a class="btn btn-success btn-sm" href="{{ route('fixtures.patterns.pdf') }}" target="_blank" rel="noopener">
            <i class="ti ti-file-type-pdf me-1"></i>
            Descargar PDF
        </a>
    </div>

    <div class="alert alert-info d-print-none">
        Estas plantillas usan el mismo metodo circular del generador. Los equipos se representan por numero para que el sorteo sea verificable antes de asignar clubes reales.
    </div>

    <style>
        .fixture-pattern-card .card-body {
            padding: .75rem;
        }

        .fixture-pattern-round {
            border: 1px solid var(--tblr-border-color);
            border-radius: .35rem;
            padding: .5rem;
            height: 100%;
        }

        .fixture-pattern-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: .35rem;
        }

        .fixture-pattern-match {
            display: grid;
            grid-template-columns: minmax(1.8rem, 1fr) auto minmax(1.8rem, 1fr);
            gap: .18rem;
            border-bottom: 1px solid var(--tblr-border-color);
            padding: .12rem 0;
            font-size: .75rem;
        }

        .fixture-pattern-home {
            text-align: right;
        }

        .fixture-pattern-away {
            text-align: left;
        }

        @media (max-width: 1199.98px) {
            .fixture-pattern-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media print {
            .fixture-pattern-card {
                break-inside: avoid;
            }

            .fixture-pattern-grid {
                grid-template-columns: repeat(7, minmax(0, 1fr));
                gap: .25rem;
            }

            .fixture-pattern-round {
                padding: .35rem;
            }

            .fixture-pattern-match {
                font-size: 10px;
            }
        }
    </style>

    @foreach ($patterns as $pattern)
        <div class="fixture-pattern-card">
            <x-ui.table-card title="{{ $pattern['team_count'] }} equipos{{ $pattern['has_bye'] ? ' · con fecha libre' : '' }}">
            <div class="fixture-pattern-grid">
                @foreach ($pattern['rounds'] as $round)
                    <div>
                        <div class="fixture-pattern-round">
                            <div class="fw-semibold small mb-1">Fecha {{ $round['number'] }}</div>
                            <ul class="list-unstyled mb-0 small">
                                @foreach ($round['pairs'] as $pair)
                                    <li class="fixture-pattern-match">
                                        <span class="fixture-pattern-home">{{ $pair['home'] }}</span>
                                        <span class="text-body-secondary">vs</span>
                                        <span class="fixture-pattern-away">{{ $pair['away'] }}</span>
                                    </li>
                                @endforeach
                                @if ($round['bye'])
                                    <li class="text-body-secondary pt-2">Libre: {{ $round['bye'] }}</li>
                                @endif
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
            </x-ui.table-card>
        </div>
    @endforeach
@endsection
