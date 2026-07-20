<?php

namespace App\Http\Requests\PlayerTransfer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewPlayerTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('player-transfers.review') ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'review_notes' => is_string($this->input('review_notes'))
                ? str($this->input('review_notes'))->squish()->toString()
                : $this->input('review_notes'),
        ]);
    }
}
