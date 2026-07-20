@extends('layouts.admin')

@section('title', 'Configuracion de pases | '.config('app.name', 'Base Admin'))
@section('page-title', 'Configuracion de pases')
@section('page-subtitle', 'Precio unico por liga deportiva')

@section('content')
    <x-ui.table-card title="Precios de pase">
        <x-slot:actions>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('player-transfers.index') }}">Volver a pases</a>
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Liga</th>
                    <th>Codigo</th>
                    <th>Precio</th>
                    <th>Siguiente correlativo</th>
                    <th class="text-end">Accion</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($companies as $company)
                    @php
                        $setting = $company->playerTransferSetting;
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $company->name }}</td>
                        <td>{{ $company->code ?: 'Sin codigo' }}</td>
                        <td>
                            <form id="transfer-setting-{{ $company->id }}" method="POST" action="{{ route('player-transfers.settings.update') }}" class="d-flex gap-2 align-items-start">
                                @csrf
                                <input type="hidden" name="company_id" value="{{ $company->id }}">
                                <div>
                                    <input class="form-control form-control-sm" name="fee_amount" type="number" min="0.01" step="0.01" value="{{ old('fee_amount', $setting?->fee_amount) }}" required>
                                    @error('fee_amount')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            </form>
                        </td>
                        <td>
                            <input class="form-control form-control-sm" form="transfer-setting-{{ $company->id }}" name="next_sequence" type="number" min="1" value="{{ old('next_sequence', $setting?->next_sequence ?? 1) }}">
                        </td>
                        <td class="text-end">
                            <button class="btn btn-primary btn-sm" form="transfer-setting-{{ $company->id }}" type="submit">Guardar</button>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="No hay ligas activas para configurar." />
                @endforelse
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
