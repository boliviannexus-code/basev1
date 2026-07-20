<?php

namespace App\Http\Requests\RedCard;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRedCardArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('red-card-articles.create') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        $companyId = CompanyContext::id($this->user()) ?? $this->integer('company_id');

        return [
            'company_id' => [CompanyContext::id($this->user()) === null ? 'required' : 'nullable', 'exists:companies,id'],
            'number' => ['required', 'string', 'max:50', Rule::unique('red_card_articles')->where('company_id', $companyId)],
            'detail' => ['required', 'string', 'max:2000'],
        ];
    }
}
