<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCompanyPublicProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->company_id !== null
            && $this->user()?->can('company-public-profile.manage') === true;
    }

    public function rules(): array
    {
        return [
            'public_name' => ['required', 'string', 'max:255'],
            'public_slug' => [
                Rule::requiredIf($this->boolean('is_public_enabled')),
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique((new Company)->getTable(), 'public_slug')->ignore($this->user()?->company_id),
            ],
            'public_description' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:80'],
            'whatsapp' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'tiktok_url' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_public_enabled' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $slug = trim((string) $this->input('public_slug', ''));

        if ($slug === '' && filled($this->input('public_name'))) {
            $slug = Str::slug((string) $this->input('public_name'));
        }

        $this->merge([
            'public_slug' => $slug !== '' ? Str::slug($slug) : null,
            'is_public_enabled' => $this->boolean('is_public_enabled'),
        ]);
    }

    public function messages(): array
    {
        return [
            'public_slug.required' => 'Necesitas un slug publico para activar la pagina publica.',
            'public_slug.unique' => 'Este slug publico ya esta en uso.',
            'public_slug.alpha_dash' => 'El slug publico solo puede contener letras, numeros, guiones y guiones bajos.',
        ];
    }
}
