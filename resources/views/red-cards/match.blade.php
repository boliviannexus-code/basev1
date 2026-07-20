@extends('layouts.admin')

@php
    $homeName = $match->homeTeam?->name ?? $match->home_seed ?? 'Equipo A';
    $awayName = $match->awayTeam?->name ?? $match->away_seed ?? 'Equipo B';
@endphp

@section('title', 'Registrar tarjetas rojas | '.config('app.name', 'Base Admin'))
@section('page-title', $homeName.' vs '.$awayName)
@section('page-subtitle', ($match->matchdayDate?->matchday?->name ?? 'Jornada').' · '.($match->matchdayDate?->date?->format('d/m/Y') ?? '-'))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('red-cards.matchdays.show', $match->matchdayDate->matchday) }}">
            <i class="ti ti-arrow-left me-1"></i>
            Partidos
        </a>
        @can('red-card-articles.view')
            <a class="btn btn-outline-danger btn-sm" href="{{ route('red-card-articles.index') }}">Articulos</a>
        @endcan
    </div>

    @if ($articles->isEmpty())
        <div class="alert alert-warning">Registra al menos un articulo antes de cargar tarjetas rojas.</div>
    @endif

    <div class="row g-3 mb-3">
        @include('red-cards.partials.team-form', ['side' => 'home', 'teamName' => $homeName, 'options' => $homeOptions])
        @include('red-cards.partials.team-form', ['side' => 'away', 'teamName' => $awayName, 'options' => $awayOptions])
    </div>

    <x-ui.table-card title="Jugadores con tarjeta roja">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Jugador</th>
                    <th>Equipo</th>
                    <th>Articulo</th>
                    <th>Detalle</th>
                    <th>Partidos</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($match->redCardSanctions as $sanction)
                    <tr>
                        <td>
                            <div class="fw-semibold">
                                <span class="badge text-bg-light border text-body me-1">#{{ $sanction->jersey_number }}</span>
                                {{ $sanction->player?->full_name ?? '-' }}
                            </div>
                            <div class="text-body-secondary small">{{ $sanction->player?->internal_code ?? '-' }}</div>
                        </td>
                        <td>{{ $sanction->team?->name ?? '-' }}</td>
                        <td>{{ $sanction->article?->number ?? '-' }}</td>
                        <td>{{ $sanction->action_detail }}</td>
                        <td>{{ $sanction->suspended_matches }}</td>
                        <td class="text-end">
                            @can('red-cards.update')
                                <form method="POST" action="{{ route('red-cards.sanctions.destroy', $sanction) }}" data-confirm-delete="Eliminar tarjeta roja?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="6" message="No hay tarjetas rojas registradas para este partido." />
                @endforelse
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
