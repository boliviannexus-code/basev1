<?php

namespace App\Http\Requests\Spaces;

use App\Models\Space;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreSpaceLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('spaces.create') === true
            && $this->route('space') instanceof Space
            && CompanyContext::belongsToUser((int) $this->route('space')->company_id, $this->user());
    }

    public function rules(): array
    {
        return [
            'country' => ['required', 'string', 'max:120'],
            'state_or_region' => ['nullable', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'zone_or_neighborhood' => ['nullable', 'string', 'max:160'],
            'address_text' => ['nullable', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'reference_text' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $address = $this->input('address_text') ?: $this->input('address');
        $reference = $this->input('reference_text') ?: $this->input('reference');

        $this->merge([
            'address' => $address,
            'address_text' => $address,
            'reference' => $reference,
            'reference_text' => $reference,
        ]);
    }
}
