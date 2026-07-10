@extends('layouts.admin')

@section('title', 'Pases | '.config('app.name', 'Base Admin'))
@section('page-title', 'Pases')
@section('page-subtitle', 'Solicitudes de pase entre equipos')

@section('content')
    <x-ui.table-card title="Solicitudes de pase">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Jugador</th>
                    <th>Origen</th>
                    <th>Solicitante</th>
                    <th>Monto</th>
                    <th>Estado</th>
                    <th class="text-end">Revision</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $transfer)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $transfer->code }}</div>
                            <div class="text-body-secondary small">{{ $transfer->created_at?->format('Y-m-d H:i') }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $transfer->player?->full_name ?? '-' }}</div>
                            <div class="text-body-secondary small">CI {{ $transfer->player?->ci ?? '-' }} · {{ $transfer->division?->name ?? '-' }}</div>
                        </td>
                        <td>{{ $transfer->fromTeam?->name ?? '-' }}</td>
                        <td>
                            <div>{{ $transfer->toTeam?->name ?? '-' }}</div>
                            <div class="text-body-secondary small">{{ $transfer->requester?->name ?? '-' }}</div>
                        </td>
                        <td>
                            <div>{{ money_format_decimal($transfer->fee_amount) }}</div>
                            @if ($transfer->collected_amount !== null)
                                <div class="text-success small">Recaudado {{ money_format_decimal($transfer->collected_amount) }}</div>
                            @endif
                        </td>
                        <td>
                            <span class="badge text-bg-{{ player_transfer_status_tone($transfer->status) }}">{{ player_transfer_status_label($transfer->status) }}</span>
                            @if ($transfer->reviewed_at)
                                <div class="text-body-secondary small">{{ $transfer->reviewed_at->format('Y-m-d H:i') }}</div>
                            @endif
                        </td>
                        <td class="text-end">
                            @if ($transfer->status === \App\Models\PlayerTransferRequest::STATUS_PENDING)
                                @can('player-transfers.review')
                                    <form method="POST" action="{{ route('player-transfers.review', $transfer) }}" class="d-flex flex-column gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <textarea class="form-control form-control-sm" name="review_notes" rows="2" placeholder="Nota de revision"></textarea>
                                        <div class="d-flex justify-content-end gap-2">
                                            <button class="btn btn-outline-danger btn-sm" name="decision" value="reject" type="submit">Rechazar</button>
                                            <button class="btn btn-success btn-sm" name="decision" value="approve" type="submit">Aprobar</button>
                                        </div>
                                    </form>
                                @else
                                    <span class="text-body-secondary">Pendiente de revision</span>
                                @endcan
                            @else
                                <div class="text-body-secondary small">{{ $transfer->reviewer?->name ?? '-' }}</div>
                                <div>{{ $transfer->review_notes ?: '-' }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="7" message="No hay solicitudes de pase registradas." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $requests->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
