<?php

namespace App\Http\Requests\Matchday;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMatchdayFiscalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('matchdays.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'start_time.required' => 'Selecciona el horario inicial.',
            'start_time.date_format' => 'El horario inicial debe tener el formato HH:MM.',
            'end_time.required' => 'Selecciona el horario final.',
            'end_time.date_format' => 'El horario final debe tener el formato HH:MM.',
            'end_time.after' => 'El horario final debe ser posterior al inicial.',
        ];
    }
}
