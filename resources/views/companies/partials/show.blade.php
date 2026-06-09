<div class="d-flex align-items-center gap-3 mb-3">
    @if ($company->logo_url)
        <img class="avatar avatar-xl" src="{{ $company->logo_url }}" alt="{{ $company->name }}">
    @else
        <span class="avatar avatar-xl bg-primary-lt text-primary"><i class="ti ti-building fs-2"></i></span>
    @endif
    <div>
        <div class="h2 mb-1">{{ $company->name }}</div>
        <div class="text-body-secondary">{{ $company->legal_name ?: 'Sin razon social' }}</div>
    </div>
</div>

<dl class="row mb-0">
    <dt class="col-sm-4">NIT/Documento</dt>
    <dd class="col-sm-8">{{ $company->tax_id ?: '-' }}</dd>
    <dt class="col-sm-4">Telefono</dt>
    <dd class="col-sm-8">{{ $company->phone ?: '-' }}</dd>
    <dt class="col-sm-4">Email</dt>
    <dd class="col-sm-8">{{ $company->email ?: '-' }}</dd>
    <dt class="col-sm-4">Direccion</dt>
    <dd class="col-sm-8">{{ $company->address ?: '-' }}</dd>
    <dt class="col-sm-4">Ciudad/Pais</dt>
    <dd class="col-sm-8">{{ trim(($company->city ?: '').' / '.($company->country ?: ''), ' /') ?: '-' }}</dd>
    <dt class="col-sm-4">Usuarios asignados</dt>
    <dd class="col-sm-8">{{ $company->users_count }}</dd>
    <dt class="col-sm-4">Pie de reporte</dt>
    <dd class="col-sm-8">{{ $company->report_footer ?: '-' }}</dd>
    <dt class="col-sm-4">Estado</dt>
    <dd class="col-sm-8"><span class="badge text-bg-{{ $company->is_active ? 'success' : 'secondary' }}">{{ $company->is_active ? 'Activo' : 'Inactivo' }}</span></dd>
    @if (\App\Support\CompanyContext::isGlobalAdmin(auth()->user()))
        <dt class="col-sm-4">Estado online</dt>
        <dd class="col-sm-8">
            <span class="badge text-bg-{{ $company->is_online_enabled_by_admin ? 'success' : 'secondary' }}">
                {{ $company->is_online_enabled_by_admin ? 'Habilitada online' : 'No habilitada online' }}
            </span>
        </dd>
    @endif
</dl>

@if (\App\Support\CompanyContext::isGlobalAdmin(auth()->user()))
    <div class="d-flex justify-content-end mt-4">
        @if ($company->is_online_enabled_by_admin)
            <form method="POST" action="{{ route('admin.companies.disable-online', $company) }}">
                @csrf
                @method('PATCH')
                <button class="btn btn-outline-warning" type="submit">Deshabilitar online</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.companies.enable-online', $company) }}">
                @csrf
                @method('PATCH')
                <button class="btn btn-outline-success" type="submit">Habilitar online</button>
            </form>
        @endif
    </div>
@endif
