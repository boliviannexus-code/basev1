<?php

namespace App\Http\Requests\ReservationChannels;

use App\Models\ReservationChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreReservationChannelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reservation-channels.manage') === true
            && $this->user()?->company_id !== null;
    }

    public function rules(): array
    {
        $companyId = (int) $this->user()?->company_id;
        $channel = $this->route('reservationChannel');

        return [
            'name' => [
                'required',
                'string',
                'max:160',
                Rule::unique((new ReservationChannel)->getTable(), 'name')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->ignore($channel?->id),
            ],
            'slug' => [
                'required',
                'string',
                'max:180',
                Rule::unique((new ReservationChannel)->getTable(), 'slug')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->ignore($channel?->id),
            ],
            'type' => ['required', Rule::in(ReservationChannel::TYPES)],
            'contact_name' => ['nullable', 'string', 'max:160'],
            'contact_email' => ['nullable', 'email', 'max:180'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe un canal con ese nombre.',
            'slug.unique' => 'Ya existe un canal con ese identificador.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $name = $this->filled('name') ? trim((string) $this->input('name')) : null;
        $slug = $this->filled('slug') ? trim((string) $this->input('slug')) : $name;

        $this->merge([
            'name' => $name,
            'slug' => $slug ? Str::slug($slug) : null,
            'type' => $this->filled('type') ? trim((string) $this->input('type')) : 'direct',
            'contact_name' => $this->filled('contact_name') ? trim((string) $this->input('contact_name')) : null,
            'contact_email' => $this->filled('contact_email') ? trim((string) $this->input('contact_email')) : null,
            'contact_phone' => $this->filled('contact_phone') ? trim((string) $this->input('contact_phone')) : null,
            'commission_percent' => $this->filled('commission_percent') ? (float) $this->input('commission_percent') : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : 0,
        ]);
    }
}
