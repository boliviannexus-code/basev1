<?php

namespace App\Http\Requests\TransportType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransportTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transport_types.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', Rule::unique('transport_types', 'title')],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
