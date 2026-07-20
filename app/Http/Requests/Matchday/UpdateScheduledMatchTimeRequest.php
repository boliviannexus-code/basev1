<?php

namespace App\Http\Requests\Matchday;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduledMatchTimeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('matchdays.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'scheduled_time' => ['required', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'scheduled_time.required' => 'Selecciona el horario del partido.',
            'scheduled_time.date_format' => 'El horario debe tener el formato HH:MM.',
        ];
    }
}
