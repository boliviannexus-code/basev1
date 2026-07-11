<?php

namespace App\Http\Requests\Meeting;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMeetingAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('meetings.update') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'present' => ['required', 'boolean'],
        ];
    }
}
