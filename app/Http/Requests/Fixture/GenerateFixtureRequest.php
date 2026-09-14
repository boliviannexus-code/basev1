<?php

namespace App\Http\Requests\Fixture;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateFixtureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('fixtures.generate') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'first_phase_rounds' => ['required', 'integer', Rule::in([1, 2])],
        ];
    }
}
