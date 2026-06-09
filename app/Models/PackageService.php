<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PackageService extends Model
{
    use BelongsToCompany;

    public const TYPES = [
        'alojamiento',
        'transporte',
        'alimentacion',
        'tour',
        'bienestar',
        'decoracion',
        'aventura',
        'equipamiento',
        'adicional',
    ];

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'type',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(AccommodationPackage::class, 'accommodation_package_service', 'service_id', 'package_id')
            ->withPivot(['id', 'inclusion_type', 'custom_name', 'custom_description', 'additional_price', 'sort_order'])
            ->withTimestamps();
    }
}
