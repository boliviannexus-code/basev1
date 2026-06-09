<?php

namespace App\Http\Requests\AccommodationPackages;

use Illuminate\Validation\Rule;

class UpdateAccommodationPackageRequest extends StoreAccommodationPackageRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $companyId = (int) $this->user()?->company_id;
        $package = $this->route('accommodationPackage') ?? $this->route('package');

        $rules['name'] = [
            'required',
            'string',
            'max:160',
            Rule::unique('accommodation_packages', 'name')
                ->where('company_id', $companyId)
                ->ignore($package),
        ];

        return $rules;
    }
}
