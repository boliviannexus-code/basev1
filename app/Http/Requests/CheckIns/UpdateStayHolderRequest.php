<?php

namespace App\Http\Requests\CheckIns;

use App\Models\Guest;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStayHolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('occupancy.manage') === true
            && $this->user()?->company_id !== null;
    }

    public function rules(): array
    {
        $companyId = (int) $this->user()?->company_id;

        return [
            'holder_guest_id' => [
                'required',
                Rule::exists((new Guest)->getTable(), 'id')->where(fn (Builder $query): Builder => $query->where('company_id', $companyId)),
            ],
        ];
    }
}
