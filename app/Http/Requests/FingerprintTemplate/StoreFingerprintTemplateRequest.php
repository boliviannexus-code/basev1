<?php

namespace App\Http\Requests\FingerprintTemplate;

use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFingerprintTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('fingerprint-templates.create') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'format' => ['nullable', 'string', 'max:100'],
            'template_data' => ['required', 'string', 'min:20'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $user = User::query()->whereKey($this->integer('user_id'))->first();

                if (! $user || ! CompanyContext::belongsToUser($user->company_id, $this->user())) {
                    $validator->errors()->add('user_id', 'Selecciona un usuario de la liga deportiva activa.');
                }
            },
        ];
    }
}
