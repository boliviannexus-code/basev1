<?php

namespace App\Http\Requests\RedCard;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRedCardArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('red-card-articles.update') ?? false;
    }

    public function rules(): array
    {
        $article = $this->route('red_card_article');

        return [
            'number' => ['required', 'string', 'max:50', Rule::unique('red_card_articles')->where('company_id', $article?->company_id)->ignore($article?->id)],
            'detail' => ['required', 'string', 'max:2000'],
        ];
    }
}
