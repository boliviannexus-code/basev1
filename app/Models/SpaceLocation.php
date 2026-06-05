<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpaceLocation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'space_id',
        'country',
        'state_or_region',
        'city',
        'zone_or_neighborhood',
        'address_text',
        'address',
        'reference',
        'reference_text',
        'latitude',
        'longitude',
        'google_place_id',
        'postal_code',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
