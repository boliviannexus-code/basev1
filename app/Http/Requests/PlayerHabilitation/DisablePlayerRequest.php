<?php

namespace App\Http\Requests\PlayerHabilitation;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class DisablePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('player-habilitations.delete') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [];
    }
}
