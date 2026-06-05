@if ($space->reviewNotes->isNotEmpty())
    @php($noteLabels = ['approval' => 'Aprobacion', 'correction' => 'Correccion solicitada', 'suspension' => 'Suspension temporal'])
    <x-ui.card title="Historial de revision" class="{{ $class ?? 'mt-3' }}">
        <div class="card-body">
            @foreach ($space->reviewNotes as $note)
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between gap-3">
                        <div class="fw-semibold">
                            {{ $noteLabels[$note->type] ?? str($note->type)->replace('_', ' ')->headline() }}
                        </div>
                        <div class="text-body-secondary small">{{ $note->created_at?->format('Y-m-d H:i') }}</div>
                    </div>
                    <div class="text-body-secondary small mb-2">{{ $note->user?->name ?: 'Sistema' }}</div>
                    <div>{{ $note->message }}</div>
                </div>
            @endforeach
        </div>
    </x-ui.card>
@endif
