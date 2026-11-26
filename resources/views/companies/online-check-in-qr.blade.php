@extends('layouts.admin')

@section('title', 'QR de check-in en línea')
@section('page-title', 'QR de check-in en línea')

@section('content')
    <div class="card"><div class="card-body">
        <h2>{{ $company->name }}</h2>
        <p class="text-body-secondary">Comparte este código para que los huéspedes accedan a la bienvenida de check-in en línea.</p>
        <div class="d-inline-block bg-white p-3 border rounded"><img width="240" height="240" alt="Código QR de check-in en línea de {{ $company->name }}" src="data:image/svg+xml;base64,{{ base64_encode($svg) }}"></div>
        <div class="mt-3"><a href="{{ $url }}" target="_blank" rel="noopener">{{ $url }}</a></div>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a class="btn btn-primary" href="data:image/svg+xml;base64,{{ base64_encode($svg) }}" download="check-in-{{ $company->id }}.svg">Descargar QR</a>
            <a class="btn btn-outline-secondary" href="{{ route('companies.show', $company) }}">Volver</a>
        </div>
    </div></div>
@endsection
