@extends('layouts.admin')
@section('title', 'Check-in en línea')
@section('page-title', 'Check-in en línea')
@section('page-subtitle', 'Revisión de datos enviados por huéspedes')
@section('content')
    <div class="alert alert-info">Compara los datos antes de aceptar. Al aceptar se actualiza la reserva; el check-in se completa por separado.</div>
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @forelse ($submissions as $item)
        @php
            $record = $item->reservationGroup ?? $item->reservation;
            $countryName = $record?->guest_birth_country_id ? ($countries[$record->guest_birth_country_id] ?? '—') : '—';
            $status = $item->review_status;
            $pending = in_array($status, ['pending', 'reviewed'], true);
        @endphp
        <div class="card mb-3">
            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <div><strong>{{ $item->guest_name }}</strong> · {{ $record?->code ?: 'Reserva no disponible' }}<div class="text-muted small">Enviado: {{ $item->created_at->format('d/m/Y H:i') }}</div></div>
                <span class="badge bg-{{ $status === 'accepted' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning') }}-lt">{{ match ($status) { 'accepted' => 'Aceptado', 'rejected' => 'Rechazado', 'reviewed' => 'Revisado anteriormente', default => 'Pendiente' } }}</span>
            </div>
            <div class="table-responsive"><table class="table card-table table-vcenter"><thead><tr><th>Dato</th><th>En el sistema</th><th>Enviado por el huésped</th></tr></thead><tbody>
                <tr><td>Documento</td><td>{{ $record?->guest_document ?: '—' }}</td><td>{{ $item->document_number }}</td></tr>
                <tr><td>País de nacimiento</td><td>{{ $countryName }}</td><td>{{ $item->country?->name ?: '—' }}</td></tr>
                <tr><td>Correo</td><td>{{ $record?->guest_email ?: '—' }}</td><td>{{ $item->email ?: 'No proporcionado' }}</td></tr>
                <tr><td>Teléfono</td><td>{{ $record?->guest_phone ?: '—' }}</td><td>{{ $item->phone ?: 'No proporcionado' }}</td></tr>
                <tr><td>Fecha de salida</td><td>{{ $record?->check_out?->format('d/m/Y') ?: '—' }}</td><td>{{ $item->confirmed_checkout->format('d/m/Y') }}</td></tr>
            </tbody></table></div>
            <div class="card-body"><div class="text-muted mb-3">Importes mostrados al huésped: {{ $item->price_local === null ? 'No disponible' : money_format_decimal($item->price_local).' '.$item->local_currency }} · {{ $item->price_usd === null ? 'No disponible' : money_format_decimal($item->price_usd).' USD' }}</div>
                @if ($pending && $record)
                    <div class="d-flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('online-check-in.review.store', $item) }}">@csrf<input type="hidden" name="decision" value="accepted"><button class="btn btn-success" type="submit">Aceptar y actualizar datos</button></form>
                        <form method="POST" action="{{ route('online-check-in.review.store', $item) }}">@csrf<input type="hidden" name="decision" value="rejected"><button class="btn btn-outline-danger" type="submit">Rechazar</button></form>
                    </div>
                    <div class="form-hint mt-2">Los campos opcionales que no se proporcionaron conservan el valor actual.</div>
                @elseif ($status === 'accepted' && $item->reservationGroup)
                    <a class="btn btn-primary" href="{{ route('check-ins.create', ['reservation_group_id' => $item->reservation_group_id]) }}">Ir al check-in</a>
                    <div class="form-hint mt-2">Disponible en la fecha de ingreso, según el estado de la reserva.</div>
                @elseif ($status === 'accepted' && $item->reservation)
                    <div class="alert alert-warning mb-0">Esta reserva individual no está vinculada a un grupo. Revisa la reserva para continuar con el check-in desde ocupación.</div>
                @endif
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-muted">No hay solicitudes de check-in en línea.</div></div>
    @endforelse
    {{ $submissions->links() }}
@endsection
