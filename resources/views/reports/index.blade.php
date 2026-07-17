@extends('layouts.admin')

@section('title', $reportTitle.' | Reportes')
@section('page-title', 'Reportes')
@section('page-subtitle', 'Reportes operativos con filtros por fecha, usuario, metodo, categoria y modulo')

@section('content')
    <x-ui.table-card title="Filtros">
        <form class="row g-3 align-items-end" method="GET" action="{{ route('reports.index') }}" autocomplete="off" data-report-filter-form>
            <div class="col-md-3 col-xl-2">
                <label class="form-label" for="report-type">Reporte</label>
                <select class="form-select" id="report-type" name="type" data-tom-select>
                    @foreach ($reportTypes as $type => $label)
                        <option value="{{ $type }}" @selected($reportType === $type)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-xl-2">
                <label class="form-label" for="report-from">Desde</label>
                <input class="form-control" id="report-from" name="from" type="date" value="{{ $filters['from']->toDateString() }}">
            </div>
            <div class="col-md-3 col-xl-2">
                <label class="form-label" for="report-to">Hasta</label>
                <input class="form-control" id="report-to" name="to" type="date" value="{{ $filters['to']->toDateString() }}">
            </div>
            <div class="col-md-3 col-xl-2">
                <label class="form-label" for="report-user">Usuario</label>
                <select class="form-select" id="report-user" name="user_id" data-tom-select data-placeholder="Todos">
                    <option value="">Todos</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((int) $filters['user_id'] === (int) $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-xl-2">
                <label class="form-label" for="report-method">Metodo</label>
                <select class="form-select" id="report-method" name="payment_method_id" data-tom-select data-placeholder="Todos">
                    <option value="">Todos</option>
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method->id }}" @selected((int) $filters['payment_method_id'] === (int) $method->id)>{{ $method->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 col-xl-2">
                <label class="form-label" for="report-category">Categoria</label>
                <select class="form-select" id="report-category" name="category_id" data-tom-select data-placeholder="Todas">
                    <option value="">Todas</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) $filters['category_id'] === (int) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-12 d-flex flex-wrap gap-2">
                <button class="btn btn-primary" type="submit"><i class="ti ti-filter"></i> Filtrar</button>
                <a class="btn btn-outline-secondary" href="{{ route('reports.index') }}"><i class="ti ti-eraser"></i> Limpiar</a>
                @can('reports.print')
                    <a class="btn btn-outline-primary ms-md-auto" href="{{ route('reports.print', request()->query()) }}" target="_blank" rel="noopener" data-report-print-link>
                        <i class="ti ti-file-type-pdf"></i>
                        Imprimir PDF
                    </a>
                @endcan
            </div>
        </form>
    </x-ui.table-card>

    <div data-report-results>
        <div class="d-flex flex-wrap gap-2 mt-3">
            @foreach ($reportTypes as $type => $label)
                <a class="btn btn-sm {{ $reportType === $type ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ route('reports.index', array_merge(request()->query(), ['type' => $type])) }}" data-report-type-link>
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="d-flex align-items-center justify-content-between gap-3 mt-3">
            <div>
                <h2 class="h3 mb-1">{{ $reportTitle }}</h2>
                <div class="text-body-secondary">{{ $company?->name }} · {{ $filters['from']->format('Y-m-d') }} al {{ $filters['to']->format('Y-m-d') }}</div>
            </div>
        </div>

        @include('reports.partials.operational-sections')
    </div>
@endsection
