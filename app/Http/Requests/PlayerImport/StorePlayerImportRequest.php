<?php

namespace App\Http\Requests\PlayerImport;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class StorePlayerImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('player-imports.create') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'dry_run' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Sube un archivo CSV exportado desde Excel.',
            'file.max' => 'El archivo no debe superar los 10 MB.',
        ];
    }
}
