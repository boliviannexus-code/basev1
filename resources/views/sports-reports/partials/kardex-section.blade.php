<x-ui.table-card :title="$title" class="mb-3">
    <table class="table table-hover align-middle">
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>
                        @if ($type === 'teams')
                            <div class="fw-semibold">{{ $row->team?->name ?? '-' }}</div>
                            <div class="text-body-secondary small">{{ $row->division?->name ?? '-' }} · {{ $row->status }} · {{ $row->joined_at?->format('d/m/Y') ?? '-' }}</div>
                        @elseif ($type === 'habilitations')
                            <div class="fw-semibold">{{ $row->team?->name ?? '-' }} · {{ $row->tournament?->name ?? '-' }}</div>
                            <div class="text-body-secondary small">{{ $row->tournamentRegistration?->category?->name ?? '-' }} · {{ $row->enabled_at?->format('d/m/Y H:i') ?? '-' }} · {{ $row->enabledBy?->name ?? '-' }}</div>
                        @elseif ($type === 'transfers')
                            <div class="fw-semibold">{{ $row->code }} · {{ player_transfer_status_label($row->status) }}</div>
                            <div class="text-body-secondary small">{{ $row->fromTeam?->name ?? '-' }} -> {{ $row->toTeam?->name ?? '-' }} · {{ $row->created_at?->format('d/m/Y H:i') ?? '-' }} · {{ $row->requester?->name ?? '-' }}</div>
                        @elseif ($type === 'red-cards')
                            <div class="fw-semibold">{{ $row->team?->name ?? '-' }} · Art. {{ $row->article?->number ?? '-' }}</div>
                            <div class="text-body-secondary small">{{ $row->action_detail }} · {{ $row->suspended_matches }} partido(s)</div>
                        @else
                            <div class="fw-semibold">Art. {{ $row->article?->number ?? '-' }} · {{ $row->status }}</div>
                            <div class="text-body-secondary small">{{ $row->reason }} · {{ $row->starts_on?->format('d/m/Y') }} - {{ $row->ends_on?->format('d/m/Y') ?? 'sin fin' }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <x-ui.empty-row colspan="1" message="Sin registros." />
            @endforelse
        </tbody>
    </table>
</x-ui.table-card>
