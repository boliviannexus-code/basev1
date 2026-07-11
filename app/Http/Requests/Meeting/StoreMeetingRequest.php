<?php

namespace App\Http\Requests\Meeting;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('meetings.create') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'meeting_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => is_string($this->input('title')) ? str($this->input('title'))->squish()->toString() : $this->input('title'),
            'meeting_date' => $this->input('meeting_date') ?: now()->toDateString(),
            'notes' => is_string($this->input('notes')) ? str($this->input('notes'))->squish()->toString() : $this->input('notes'),
        ]);
    }
}
