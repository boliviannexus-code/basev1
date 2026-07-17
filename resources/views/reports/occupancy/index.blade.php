@extends('layouts.admin')

@section('title', $reportTitle.' | Reportes')
@section('page-title', 'Reporte de ocupabilidad')
@section('page-subtitle', 'Analisis operativo independiente por fechas, usuario, alojamiento y estado')

@section('content')
    <div class="d-flex flex-wrap gap-2 mb-3">
        <a class="btn btn-sm {{ $isDaily ? 'btn-outline-secondary' : 'btn-primary' }}" href="{{ route('reports.occupancy.index', request()->except(['daily', 'date'])) }}">
            <i class="ti ti-chart-bar"></i>
            General
        </a>
        <a class="btn btn-sm {{ $isDaily ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('reports.occupancy.index', array_merge(request()->query(), ['daily' => 1, 'date' => $dailyDate->toDateString()])) }}">
            <i class="ti ti-calendar-stats"></i>
            Diario
        </a>
    </div>

    <x-ui.table-card title="Filtros">
        <form class="row g-3 align-items-end" method="GET" action="{{ route('reports.occupancy.index') }}" autocomplete="off" data-report-filter-form>
            @if ($isDaily)
                <input type="hidden" name="daily" value="1">
                <div class="col-md-3 col-xl-2">
                    <label class="form-label" for="occupancy-date">Fecha</label>
                    <input class="form-control" id="occupancy-date" name="date" type="date" value="{{ $dailyDate->toDateString() }}">
                </div>
            @else
                <div class="col-md-3 col-xl-2">
                    <label class="form-label" for="occupancy-from">Desde</label>
                    <input class="form-control" id="occupancy-from" name="from" type="date" value="{{ $filters['from']->toDateString() }}">
                </div>
                <div class="col-md-3 col-xl-2">
                    <label class="form-label" for="occupancy-to">Hasta</label>
                    <input class="form-control" id="occupancy-to" name="to" type="date" value="{{ $filters['to']->toDateString() }}">
                </div>
            @endif
            <div class="col-md-3 col-xl-2">
                <label class="form-label" for="occupancy-user">Usuario</label>
                <select class="form-select" id="occupancy-user" name="user_id" data-tom-select data-placeholder="Todos">
                    <option value="">Todos</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((int) $filters['user_id'] === (int) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-xl-3">
                <label class="form-label" for="occupancy-space">Alojamiento</label>
                <select class="form-select" id="occupancy-space" name="space_id" data-tom-select data-placeholder="Todos">
                    <option value="">Todos</option>
                    @foreach ($spaces as $space)
                        <option value="{{ $space->id }}" @selected((int) $filters['space_id'] === (int) $space->id)>{{ $space->title ?: $space->name }}</option>
                    @endforeach
                </select>
            </div>
            @unless ($isDaily)
                <div class="col-md-3 col-xl-2">
                    <label class="form-label" for="occupancy-status">Estado</label>
                    <select class="form-select" id="occupancy-status" name="occupancy_status" data-tom-select data-placeholder="Todos">
                        <option value="">Todos</option>
                        @foreach ($occupancyStatuses as $status => $label)
                            <option value="{{ $status }}" @selected($filters['occupancy_status'] === $status)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endunless
            <div class="col-md-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit"><i class="ti ti-filter"></i> Filtrar</button>
                <a class="btn btn-outline-secondary" href="{{ route('reports.occupancy.index', $isDaily ? ['daily' => 1, 'date' => now()->toDateString()] : []) }}"><i class="ti ti-eraser"></i> Limpiar</a>
                @can('reports.print')
                    <a class="btn btn-outline-primary ms-md-auto" href="{{ route('reports.occupancy.print', request()->query()) }}" target="_blank" rel="noopener" data-report-print-link>
                        <i class="ti ti-file-type-pdf"></i>
                        Imprimir PDF
                    </a>
                @endcan
            </div>
        </form>
    </x-ui.table-card>

    <div data-report-results>
        @if ($isDaily)
            <div class="text-center my-4">
                <div class="text-uppercase text-body-secondary fw-semibold small">Reporte diario de ocupabilidad</div>
                <h2 class="display-6 mb-1">{{ $dailyDate->format('d/m/Y') }}</h2>
                <div class="text-body-secondary">{{ $company?->name }}</div>
            </div>

            @include('reports.occupancy.daily-sections')
        @else
            <div class="d-flex align-items-center justify-content-between gap-3 mt-3">
                <div>
                    <h2 class="h3 mb-1">{{ $reportTitle }}</h2>
                    <div class="text-body-secondary">{{ $company?->name }} · {{ $filters['from']->format('Y-m-d') }} al {{ $filters['to']->format('Y-m-d') }}</div>
                </div>
            </div>

            @include('reports.occupancy.sections')
        @endif
    </div>
@endsection
