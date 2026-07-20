@extends('layouts.admin')

@section('title', 'Categorias del fixture | '.config('app.name', 'Base Admin'))
@section('page-title', 'Categorias')
@section('page-subtitle', $tournament->name)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('fixtures.index') }}">
            <i class="ti ti-arrow-left me-1"></i>
            Torneos
        </a>
        <span class="text-body-secondary small">{{ $tournament->season?->name ?? '-' }} · {{ $tournament->division?->name ?? '-' }}</span>
    </div>

    <x-ui.table-card title="Categorias del torneo">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th>Equipos inscritos</th>
                    <th>Estado</th>
                    <th class="text-end">Accion</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td class="fw-semibold">{{ $category->name }}</td>
                        <td>{{ $category->registrations_count }}</td>
                        <td>
                            <span class="badge text-bg-{{ $category->registrations_count > 0 ? 'success' : 'secondary' }}">
                                {{ $category->registrations_count > 0 ? 'Con equipos' : 'Sin equipos' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-primary btn-sm @if ($category->registrations_count < 1) disabled @endif" href="{{ route('fixtures.series', ['tournament' => $tournament, 'category' => $category]) }}" @if ($category->registrations_count < 1) aria-disabled="true" @endif>
                                <i class="ti ti-arrow-right me-1"></i>
                                Series
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="4" message="Este torneo no tiene categorias habilitadas." />
                @endforelse
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
