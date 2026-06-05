<?php

namespace App\Http\Requests\Team;

use App\Models\Team;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('team');

        return ($this->user()?->can('teams.update') ?? false)
            && CompanyContext::canOperate($this->user())
            && $team !== null
            && CompanyContext::belongsToUser($team->company_id, $this->user());
    }

    public function rules(): array
    {
        $team = $this->route('team');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('teams')->where('company_id', $team?->company_id)->whereNull('deleted_at')->ignore($team?->id)],
            'founded_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $team = $this->route('team');
                $name = (string) $this->input('name');

                if ($team && Team::query()
                    ->where('company_id', $team->company_id)
                    ->where('name_normalized', Team::normalizeName($name))
                    ->whereKeyNot($team->id)
                    ->whereNull('deleted_at')
                    ->exists()) {
                    $validator->errors()->add('name', 'Ya existe otro equipo con ese nombre en la misma liga deportiva.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? str($this->input('name'))->squish()->toString() : $this->input('name'),
            'founded_at' => $this->input('founded_at') ?: now()->toDateString(),
        ]);
    }
}
