@extends('layouts.admin')

@section('title', 'Items de control | '.config('app.name', 'Base Admin'))
@section('page-title', 'Items de control')
@section('page-subtitle', 'Controles configurables para el registro inicial de partidos')

@section('content')
    <div class="row g-3">
        @forelse ($companies as $company)
            <div class="col-12">
                <form class="card" method="POST" action="{{ route('league-settings.match-control-items.update') }}">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-3">
                        <div>
                            <h3 class="card-title mb-0">{{ $company->name }}</h3>
                            <div class="text-body-secondary small">Universales: {{ collect($universalMatchControlItems)->values()->implode(', ') }}</div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="input-group input-group-sm" style="max-width: 480px;">
                                <span class="input-group-text"><i class="ti ti-plus"></i></span>
                                <input class="form-control" name="new_match_control_item" placeholder="Nuevo item, ej. Cintillo" maxlength="120" @cannot('league-settings.update') readonly @endcannot>
                                <span class="input-group-text">Bs</span>
                                <input class="form-control text-end" name="new_match_control_item_absence_cost" type="number" min="0" step="0.01" value="0.00" aria-label="Costo por ausencia" @cannot('league-settings.update') readonly @endcannot>
                            </div>
                            @can('league-settings.update')
                                <button class="btn btn-primary btn-sm" type="submit">
                                    <i class="ti ti-device-floppy me-1"></i>
                                    Guardar
                                </button>
                            @endcan
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Item por liga</th>
                                    <th class="text-center" style="width: 110px;">Orden</th>
                                    <th class="text-end" style="width: 170px;">Costo por ausencia (Bs)</th>
                                    <th class="text-center" style="width: 110px;">Activo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($company->matchControlItems as $item)
                                    <tr>
                                        <td>
                                            <input type="hidden" name="match_control_items[{{ $item->id }}][id]" value="{{ $item->id }}">
                                            <input class="form-control form-control-sm @error('match_control_items.'.$item->id.'.label') is-invalid @enderror" name="match_control_items[{{ $item->id }}][label]" value="{{ old('match_control_items.'.$item->id.'.label', $item->label) }}" maxlength="120" @cannot('league-settings.update') readonly @endcannot required>
                                            @error('match_control_items.'.$item->id.'.label')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td>
                                            <input class="form-control form-control-sm text-end @error('match_control_items.'.$item->id.'.sort_order') is-invalid @enderror" name="match_control_items[{{ $item->id }}][sort_order]" type="number" min="0" max="999" value="{{ old('match_control_items.'.$item->id.'.sort_order', $item->sort_order) }}" @cannot('league-settings.update') readonly @endcannot required>
                                            @error('match_control_items.'.$item->id.'.sort_order')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td><input class="form-control form-control-sm text-end @error('match_control_items.'.$item->id.'.absence_cost') is-invalid @enderror" name="match_control_items[{{ $item->id }}][absence_cost]" type="number" min="0" step="0.01" value="{{ old('match_control_items.'.$item->id.'.absence_cost', $item->absence_cost) }}" @cannot('league-settings.update') readonly @endcannot required>@error('match_control_items.'.$item->id.'.absence_cost')<div class="invalid-feedback">{{ $message }}</div>@enderror</td>
                                        <td class="text-center">
                                            <input type="hidden" name="match_control_items[{{ $item->id }}][is_active]" value="0">
                                            <input class="form-check-input" name="match_control_items[{{ $item->id }}][is_active]" type="checkbox" value="1" @checked(old('match_control_items.'.$item->id.'.is_active', $item->is_active)) @cannot('league-settings.update') disabled @endcannot>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-body-secondary">Sin items propios de esta liga.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info">No hay ligas activas para configurar.</div>
            </div>
        @endforelse
    </div>
@endsection
