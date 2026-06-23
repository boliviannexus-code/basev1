<?php

namespace App\Http\Requests\Spaces\Shared;

use App\Models\SharedSpaceType;
use App\Models\Space;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSharedSpaceDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('spaces.create') === true
            && $this->route('space') instanceof Space
            && (int) $this->route('space')->company_id === (int) $this->user()->company_id;
    }

    public function rules(): array
    {
        $space = $this->route('space');

        return [
            'shared_space_type_id' => [
                'required',
                Rule::exists((new SharedSpaceType)->getTable(), 'id')->where('is_active', true),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique((new Space)->getTable(), 'name')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($space->id),
            ],
            'short_description' => ['required', 'string', 'max:300'],
            'full_description' => ['required', 'string', 'max:2000'],
        ];
    }
}
