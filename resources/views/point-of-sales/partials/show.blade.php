<dl class="row mb-0">
    <dt class="col-sm-4">Codigo</dt>
    <dd class="col-sm-8">{{ $pointOfSale->code }}</dd>
    <dt class="col-sm-4">Comprobante</dt>
    <dd class="col-sm-8">
        {{ $pointOfSale->receipt_prefix ?: $pointOfSale->code }}-{{ str_pad((string) $pointOfSale->receipt_next_number, (int) ($pointOfSale->receipt_digits ?: 6), '0', STR_PAD_LEFT) }}
    </dd>
    <dt class="col-sm-4">Nombre</dt>
    <dd class="col-sm-8">{{ $pointOfSale->name }}</dd>
    <dt class="col-sm-4">Liga deportiva</dt>
    <dd class="col-sm-8">{{ $pointOfSale->company?->name ?? 'Sin liga deportiva' }}</dd>
    <dt class="col-sm-4">Sucursal</dt>
    <dd class="col-sm-8">{{ $pointOfSale->branch?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Almacen vinculado</dt>
    <dd class="col-sm-8">{{ $pointOfSale->warehouse?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Usuarios asignados</dt>
    <dd class="col-sm-8">
        @forelse ($pointOfSale->users as $user)
            <span class="badge text-bg-light">{{ $user->name }}</span>
        @empty
            <span class="text-body-secondary">Sin usuarios</span>
        @endforelse
    </dd>
    <dt class="col-sm-4">Estado</dt>
    <dd class="col-sm-8"><span class="badge text-bg-{{ $pointOfSale->is_active ? 'success' : 'secondary' }}">{{ $pointOfSale->is_active ? 'Activo' : 'Inactivo' }}</span></dd>
    <dt class="col-sm-4">Creado</dt>
    <dd class="col-sm-8">{{ $pointOfSale->created_at?->format('Y-m-d H:i') }}</dd>
</dl>
