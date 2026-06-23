<?php

namespace App\Http\Requests\Spaces;

use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;

class StoreSpaceDescriptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('spaces.create') === true
            && $this->route('space') instanceof Space
            && (int) $this->route('space')->company_id === (int) $this->user()->company_id;
    }

    public function rules(): array
    {
        return [
            'short_description' => ['required', 'string', 'max:300'],
            'full_description' => ['required', 'string', 'max:2000'],
        ];
    }
}
