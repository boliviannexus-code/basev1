<?php

namespace App\Http\Requests\PlayerTransfer;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class StorePlayerTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('player-transfers.create') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'tournament_id' => ['required', 'integer'],
            'to_team_id' => ['required', 'integer'],
            'player_id' => ['required', 'integer'],
            'requested_note' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'requested_note' => is_string($this->input('requested_note'))
                ? str($this->input('requested_note'))->squish()->toString()
                : $this->input('requested_note'),
        ]);
    }
}
