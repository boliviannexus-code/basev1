<?php

namespace App\Http\Requests\DatabaseBackup;

use Illuminate\Foundation\Http\FormRequest;

class RestoreDatabaseBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('database-backups.restore') ?? false;
    }

    public function rules(): array
    {
        return [
            'backup' => ['nullable', 'string', 'max:255', 'required_without:file'],
            'file' => ['nullable', 'file', 'extensions:sql,txt', 'max:102400', 'required_without:backup'],
        ];
    }
}
