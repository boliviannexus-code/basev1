<?php

namespace App\Http\Requests\User;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('users.edit') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id ?? $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'transaction_pin' => ['nullable', 'digits:4', 'confirmed'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['exists:roles,name'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('company_id') || CompanyContext::canAssignNoCompany($this->user())) {
                return;
            }

            $validator->errors()->add('company_id', 'Solo un super administrador puede asignar usuarios sin empresa.');
        });
    }
}
