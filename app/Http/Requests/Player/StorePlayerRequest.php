<?php

namespace App\Http\Requests\Player;

use App\Models\Player;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('players.create') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'ci' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['required', 'date', 'before:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (Player::query()->where('ci_normalized', Player::normalizeCi((string) $this->input('ci')))->exists()) {
                    $validator->errors()->add('ci', 'Ya existe un jugador registrado con este CI.');
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
        ]);
    }
}
