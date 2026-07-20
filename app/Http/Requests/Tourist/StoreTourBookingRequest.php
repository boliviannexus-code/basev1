<?php

namespace App\Http\Requests\Tourist;

use Illuminate\Foundation\Http\FormRequest;

class StoreTourBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'travel_date' => ['required', 'date', 'after_or_equal:today'],
            'people' => ['required', 'integer', 'min:1', 'max:50'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'country' => ['required', 'string', 'max:120'],
            'special_requirements' => ['nullable', 'string', 'max:1200'],
        ];
    }
}
