@extends('layouts.admin')

@section('title', 'Series del fixture | '.config('app.name', 'Base Admin'))
@section('page-title', 'Series')
@section('page-subtitle', $tournament->name.' · '.$category->name)

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('fixtures.categories', $tournament) }}">
            <i class="ti ti-arrow-left me-1"></i>
            Categorias
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="text-body-secondary small">{{ $tournament->season?->name ?? '-' }} · {{ $tournament->division?->name ?? '-' }}</span>
            <a class="btn btn-primary btn-sm @if ($series->isEmpty() || $series->sum('team_count') < 2) disabled @endif" href="{{ route('fixtures.configure', ['tournament' => $tournament, 'category' => $category]) }}" @if ($series->isEmpty() || $series->sum('team_count') < 2) aria-disabled="true" @endif>
                <i class="ti ti-settings me-1"></i>
                Configurar modalidad
            </a>
        </div>
    </div>

    <x-ui.table-card title="Series de la categoria">
        <div class="alert alert-info py-2 mb-3">
            La configuracion del fixture sera global para esta categoria. Todas las series usaran la misma modalidad, ruedas y cantidad de clasificados.
        </div>
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Serie</th>
                    <th>Equipos</th>
                    <th class="text-end">Detalle</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($series as $serie)
                    <tr>
                        <td class="fw-semibold">{{ $serie['label'] }}</td>
                        <td>{{ $serie['team_count'] }}</td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('fixtures.series.teams', ['tournament' => $tournament, 'category' => $category, 'series' => $serie['key']]) }}" data-modal-url="{{ route('fixtures.series.teams', ['tournament' => $tournament, 'category' => $category, 'series' => $serie['key']]) }}" data-modal-title="Equipos de {{ $serie['label'] }}">
                                <i class="ti ti-list-details me-1"></i>
                                Ver equipos
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="3" message="No hay equipos inscritos en series para esta categoria." />
                @endforelse
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
