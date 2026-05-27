@extends('layouts.admin')

@section('title', 'Prueba biometrica | '.config('app.name', 'Base Admin'))
@section('page-title', 'Prueba biometrica')
@section('page-subtitle', 'Modulo experimental aislado')

@section('content')
    <div class="row g-3">
        <div class="col-lg-7">
            <x-ui.form-panel title="Captura de huella">
                <div
                    data-biometric-test
                    data-enroll-url="{{ route('biometric.enroll') }}"
                    data-verify-url="{{ route('biometric.verify') }}"
                >
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="biometric-finger-position">Dedo</label>
                            <select class="form-select" id="biometric-finger-position" data-finger-position>
                                <option value="">No especificado</option>
                                <option value="right_thumb">Pulgar derecho</option>
                                <option value="right_index">Indice derecho</option>
                                <option value="right_middle">Medio derecho</option>
                                <option value="right_ring">Anular derecho</option>
                                <option value="right_little">Menique derecho</option>
                                <option value="left_thumb">Pulgar izquierdo</option>
                                <option value="left_index">Indice izquierdo</option>
                                <option value="left_middle">Medio izquierdo</option>
                                <option value="left_ring">Anular izquierdo</option>
                                <option value="left_little">Menique izquierdo</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Formato</label>
                            <input class="form-control" value="PNG base64" disabled>
                        </div>

                        <div class="col-12">
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-outline-info btn-sm" type="button" data-detect-reader>Detectar lector</button>
                                <button class="btn btn-outline-primary btn-sm" type="button" data-capture-fingerprint>Capturar huella</button>
                                <button class="btn btn-primary btn-sm" type="button" data-save-fingerprint disabled>Guardar huella</button>
                                <button class="btn btn-outline-success btn-sm" type="button" data-verify-fingerprint disabled>Verificar huella</button>
                            </div>
                            <div class="form-hint mt-2" data-biometric-status>Listo para detectar lector.</div>
                        </div>

                        <div class="col-12">
                            <div class="border rounded p-3 text-center bg-light">
                                <img class="img-fluid d-none" alt="Huella capturada" data-fingerprint-preview style="max-height: 260px;">
                                <div class="text-body-secondary" data-fingerprint-empty>Sin huella capturada.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui.form-panel>
        </div>

        <div class="col-lg-5">
            <x-ui.table-card title="Mis huellas de prueba">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Dedo</th>
                            <th>Calidad</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($fingerprints as $fingerprint)
                            <tr>
                                <td>{{ $fingerprint->finger_position ?: '-' }}</td>
                                <td>{{ $fingerprint->quality_score ?? '-' }}</td>
                                <td>{{ $fingerprint->enrolled_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            </tr>
                        @empty
                            <x-ui.empty-row colspan="3" message="Aun no tienes huellas de prueba." />
                        @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/biometric-test.js')
@endpush
