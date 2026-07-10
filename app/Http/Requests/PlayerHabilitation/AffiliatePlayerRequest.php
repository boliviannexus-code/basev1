<?php

namespace App\Http\Requests\PlayerHabilitation;

use App\Models\Player;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AffiliatePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('player-habilitations.create') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'tournament_id' => ['required', 'integer'],
            'team_id' => ['required', 'integer'],
            'ci' => ['required', 'string', 'max:50'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'maternal_name' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'joined_at' => ['nullable', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', Rule::in(['1', '0', 1, 0, true, false])],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (Player::query()
                    ->forCompany(CompanyContext::id($this->user()))
                    ->where('ci_normalized', Player::normalizeCi((string) $this->input('ci')))
                    ->exists()) {
                    return;
                }

                foreach (['first_name', 'birth_date'] as $field) {
                    if (! $this->filled($field)) {
                        $validator->errors()->add($field, 'Este dato es obligatorio cuando el CI no existe.');
                    }
                }

                if (! $this->filled('last_name') && ! $this->filled('maternal_name')) {
                    $message = 'Registra al menos un apellido cuando el CI no existe.';

                    $validator->errors()->add('last_name', $message);
                    $validator->errors()->add('maternal_name', $message);
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'ci' => is_string($this->input('ci')) ? str($this->input('ci'))->squish()->upper()->toString() : $this->input('ci'),
            'first_name' => is_string($this->input('first_name')) ? str($this->input('first_name'))->squish()->toString() : $this->input('first_name'),
            'last_name' => is_string($this->input('last_name')) ? str($this->input('last_name'))->squish()->toString() : $this->input('last_name'),
            'maternal_name' => is_string($this->input('maternal_name')) ? str($this->input('maternal_name'))->squish()->toString() : $this->input('maternal_name'),
        ]);
    }
}
