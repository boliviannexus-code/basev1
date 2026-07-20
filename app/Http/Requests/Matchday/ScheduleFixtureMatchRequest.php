<?php

namespace App\Http\Requests\Matchday;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleFixtureMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('matchdays.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'fixture_match_id' => ['required', 'integer', 'exists:fixture_matches,id'],
            'scheduled_time' => ['required', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'fixture_match_id.required' => 'Selecciona un partido del fixture.',
            'fixture_match_id.exists' => 'El partido seleccionado no existe.',
            'scheduled_time.required' => 'Selecciona el horario del partido.',
            'scheduled_time.date_format' => 'El horario debe tener el formato HH:MM.',
        ];
    }
}
