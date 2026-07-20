<?php

namespace App\Http\Requests\Team;

use App\Models\Team;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('teams.create') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        $companyId = CompanyContext::id($this->user()) ?? $this->integer('company_id');

        return [
            'company_id' => [CompanyContext::id($this->user()) === null ? 'required' : 'nullable', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('teams')->where('company_id', $companyId)->whereNull('deleted_at')],
            'founded_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $companyId = CompanyContext::id($this->user()) ?? $this->integer('company_id');
                $name = (string) $this->input('name');

                if ($companyId && Team::query()
                    ->where('company_id', $companyId)
                    ->where('name_normalized', Team::normalizeName($name))
                    ->whereNull('deleted_at')
                    ->exists()) {
                    $validator->errors()->add('name', 'Ya existe un equipo con ese nombre en la misma liga deportiva.');
                }

                $similarTeam = $companyId ? Team::query()
                    ->where('company_id', $companyId)
                    ->where('name_match_key', Team::matchKey($name))
                    ->whereNull('deleted_at')
                    ->first(['name']) : null;

                if ($similarTeam) {
                    $validator->errors()->add('name', 'Ya existe un equipo con una coincidencia fuerte: '.$similarTeam->name.'.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? Team::formatName($this->input('name')) : $this->input('name'),
            'founded_at' => $this->input('founded_at') ?: now()->toDateString(),
        ]);
    }
}
