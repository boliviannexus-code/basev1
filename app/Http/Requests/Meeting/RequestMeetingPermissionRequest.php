<?php

namespace App\Http\Requests\Meeting;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class RequestMeetingPermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('meetings.update') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'permission_reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
