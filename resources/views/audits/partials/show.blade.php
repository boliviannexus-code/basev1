@php
    $fields = collect(array_keys($oldValues))
        ->merge(array_keys($newValues))
        ->unique()
        ->values();
@endphp

<div class="mb-3">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="badge text-bg-primary">{{ $eventLabel }}</span>
        <span class="fw-semibold">{{ $auditableLabel }}</span>
    </div>
    <div class="text-body-secondary small mt-1">
        Registrado el {{ $audit->created_at?->format('d/m/Y') }} a las {{ $audit->created_at?->format('H:i:s') }} · Por {{ $audit->user?->name ?? 'Sistema' }}
    </div>
</div>

<div class="mb-3">
    <div><strong>Empresa:</strong> {{ $context['company'] }}</div>
    @if ($context['room'])
        <div><strong>Habitación{{ $context['room_is_current'] ? ' actual' : '' }}:</strong> {{ $context['room'] }}</div>
    @endif
</div>

<p class="mb-3">{{ $presenter->summary($audit) }}</p>
<p class="text-body-secondary small">Los nombres de habitaciones, alojamientos y métodos de pago se muestran según sus datos actuales.</p>

<div class="table-responsive">
    <table class="table table-sm table-vcenter mb-0">
        <thead>
            <tr>
                <th>Campo</th>
                <th>Antes</th>
                <th>Despues</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($fields as $field)
                <tr>
                    <td class="fw-semibold">{{ $presenter->label($field) }}</td>
                    <td class="text-break">{{ $presenter->value($field, $oldValues[$field] ?? null, $audit->company_id) }}</td>
                    <td class="text-break">{{ $presenter->value($field, $newValues[$field] ?? null, $audit->company_id) }}</td>
                </tr>
            @empty
                <tr>
                    <td class="text-center text-body-secondary py-4" colspan="3">Este evento no registro cambios de valores.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
