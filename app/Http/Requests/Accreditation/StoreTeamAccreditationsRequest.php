<?php

namespace App\Http\Requests\Accreditation;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTeamAccreditationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('accreditations.update') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'delegates' => ['required', 'array'],
            'delegates.*.ci' => ['nullable', 'string', 'max:50'],
            'delegates.*.first_name' => ['nullable', 'string', 'max:255'],
            'delegates.*.last_name' => ['nullable', 'string', 'max:255'],
            'delegates.*.maternal_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ($this->input('delegates', []) as $slot => $delegate) {
                    if (! in_array((int) $slot, [1, 2, 3], true)) {
                        $validator->errors()->add("delegates.{$slot}", 'El delegado seleccionado no es valido.');

                        continue;
                    }

                    $hasAnyValue = collect($delegate)
                        ->filter(fn ($value): bool => filled($value))
                        ->isNotEmpty();

                    if (! $hasAnyValue) {
                        continue;
                    }

                    foreach (['ci' => 'carnet', 'first_name' => 'nombre', 'last_name' => 'apellido paterno'] as $field => $label) {
                        if (blank($delegate[$field] ?? null)) {
                            $validator->errors()->add("delegates.{$slot}.{$field}", 'El '.$label.' del delegado '.$slot.' es obligatorio.');
                        }
                    }
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $delegates = collect($this->input('delegates', []))
            ->only([1, 2, 3, '1', '2', '3'])
            ->mapWithKeys(function (array $delegate, int|string $slot): array {
                return [(int) $slot => [
                    'ci' => is_string($delegate['ci'] ?? null) ? str($delegate['ci'])->squish()->upper()->toString() : ($delegate['ci'] ?? null),
                    'first_name' => is_string($delegate['first_name'] ?? null) ? str($delegate['first_name'])->squish()->toString() : ($delegate['first_name'] ?? null),
                    'last_name' => is_string($delegate['last_name'] ?? null) ? str($delegate['last_name'])->squish()->toString() : ($delegate['last_name'] ?? null),
                    'maternal_name' => is_string($delegate['maternal_name'] ?? null) ? str($delegate['maternal_name'])->squish()->toString() : ($delegate['maternal_name'] ?? null),
                ]];
            })
            ->all();

        $this->merge(['delegates' => $delegates]);
    }
}
