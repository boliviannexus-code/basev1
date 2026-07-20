<div class="mb-3">
    <div class="text-body-secondary small">Torneo</div>
    <div class="fw-semibold">{{ $tournament->name }} · {{ $category->name }} · {{ $series['label'] }}</div>
</div>

<div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead>
            <tr>
                <th style="width: 6rem;">Numero</th>
                <th>Equipo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($series['registrations'] as $registration)
                <tr>
                    <td class="fw-semibold">#{{ $registration->team_number }}</td>
                    <td>{{ $registration->team?->name ?? '-' }}</td>
                </tr>
            @empty
                <x-ui.empty-row colspan="2" message="No hay equipos inscritos en esta serie." />
            @endforelse
        </tbody>
    </table>
</div>
