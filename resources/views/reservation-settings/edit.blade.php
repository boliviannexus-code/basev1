@extends('layouts.admin')

@section('title', 'Configuracion de reservas | '.config('app.name', 'Base Admin'))
@section('page-title', 'Configuracion de reservas')
@section('page-subtitle', 'Adelantos y alertas operativas de salida')

@section('content')
    <div class="row g-3">
        <div class="col-lg-5">
            <x-ui.card title="Adelanto requerido">
                <div class="card-body">
                    <form method="POST" action="{{ route('reservation-settings.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label" for="reservation_advance_percentage">Porcentaje de adelanto</label>
                            <div class="input-group">
                                <input
                                    class="form-control @error('reservation_advance_percentage') is-invalid @enderror"
                                    id="reservation_advance_percentage"
                                    name="reservation_advance_percentage"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value="{{ old('reservation_advance_percentage', $company->reservation_advance_percentage ?? $defaultPercentage) }}"
                                    required
                                >
                                <span class="input-group-text">%</span>
                                <div class="invalid-feedback">{{ $errors->first('reservation_advance_percentage') }}</div>
                            </div>
                            <div class="form-text">Se aplica a nuevas reservas publicas. Las reservas ya creadas conservan su adelanto original.</div>
                        </div>

                        <hr>
                        <h4>Alertas de check-out</h4>
                        <div class="mb-3">
                            <label class="form-label" for="check_out_time">Horario limite de check-out</label>
                            <input class="form-control @error('check_out_time') is-invalid @enderror" id="check_out_time" name="check_out_time" type="time" value="{{ old('check_out_time', substr((string) ($company->check_out_time ?? '11:00'), 0, 5)) }}" required>
                            <div class="invalid-feedback">{{ $errors->first('check_out_time') }}</div>
                            <div class="form-text">A partir de esta hora se alertara sobre las habitaciones que no registraron su salida.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="check_out_alert_snooze_minutes">Recordar nuevamente despues de</label>
                            <div class="input-group">
                                <input class="form-control @error('check_out_alert_snooze_minutes') is-invalid @enderror" id="check_out_alert_snooze_minutes" name="check_out_alert_snooze_minutes" type="number" min="1" max="1440" value="{{ old('check_out_alert_snooze_minutes', $company->check_out_alert_snooze_minutes ?? 30) }}" required>
                                <span class="input-group-text">minutos</span>
                                <div class="invalid-feedback">{{ $errors->first('check_out_alert_snooze_minutes') }}</div>
                            </div>
                        </div>

                        <button class="btn btn-primary w-100" type="submit">
                            <i class="ti ti-device-floppy me-1"></i>Guardar configuracion
                        </button>
                    </form>
                </div>
            </x-ui.card>
        </div>

        <div class="col-lg-7">
            <x-ui.card title="Resumen">
                <div class="card-body">
                    @php
                        $percentage = (float) ($company->reservation_advance_percentage ?? $defaultPercentage);
                        $exampleTotal = 1000;
                        $exampleAdvance = round($exampleTotal * ($percentage / 100), 2);
                    @endphp

                    <dl class="row mb-0">
                        <dt class="col-sm-5">Porcentaje activo</dt>
                        <dd class="col-sm-7">{{ number_format($percentage, 2) }}%</dd>
                        <dt class="col-sm-5">Ejemplo sobre 1,000.00 Bs</dt>
                        <dd class="col-sm-7">{{ money_format_decimal($exampleAdvance) }} Bs de adelanto</dd>
                        <dt class="col-sm-5">Saldo del ejemplo</dt>
                        <dd class="col-sm-7">{{ money_format_decimal($exampleTotal - $exampleAdvance) }} Bs</dd>
                    </dl>
                </div>
            </x-ui.card>
        </div>
    </div>
@endsection
