<?php

namespace App\Http\Requests\Matchday;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMatchdayDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('matchdays.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'court_id' => ['required', 'integer', 'exists:courts,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required' => 'Selecciona la fecha de juego.',
            'date.date' => 'La fecha seleccionada no es valida.',
            'court_id.required' => 'Selecciona la cancha donde se jugara.',
            'court_id.exists' => 'La cancha seleccionada no existe.',
        ];
    }
}
