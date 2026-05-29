<?php

namespace App\Http\Requests\TransportType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTransportTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transport_types.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', Rule::unique('transport_types', 'title')->ignore($this->route('transportType'))],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
